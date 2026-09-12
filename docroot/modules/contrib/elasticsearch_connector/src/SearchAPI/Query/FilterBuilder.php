<?php

namespace Drupal\elasticsearch_connector\SearchAPI\Query;

use Drupal\elasticsearch_connector\Plugin\search_api\backend\ElasticSearchBackend;
use Drupal\search_api\Query\Condition;
use Drupal\search_api\Query\ConditionGroupInterface;
use Drupal\search_api\SearchApiException;
use Psr\Log\LoggerInterface;

/**
 * Provides a query filter builder.
 *
 * @see https://www.elastic.co/docs/reference/query-languages/query-dsl/query-dsl-range-query
 */
class FilterBuilder {

  /**
   * Creates a new filter builder.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(
    protected LoggerInterface $logger,
  ) {
  }

  /**
   * Recursively parse Search API condition group.
   *
   * @param \Drupal\search_api\Query\ConditionGroupInterface $condition_group
   *   The condition group object that holds all conditions that should be
   *   expressed as filters.
   * @param \Drupal\search_api\Item\FieldInterface[] $index_fields
   *   An array of all indexed fields for the index, keyed by field identifier.
   * @param array{prefix?: string, suffix?: string, fuzziness?: string} $querySettings
   *   The query settings.
   *
   * @return array
   *   Array of filter parameters to apply to query based on the given Search
   *   API condition group.
   *
   * @throws \Drupal\search_api\SearchApiException
   *   Thrown if an invalid condition occurs.
   */
  public function buildFilters(ConditionGroupInterface $condition_group, array $index_fields, array $querySettings = []) {

    $filters = [
      'filters' => [],
      'post_filters' => [],
      'facets_post_filters' => [],
    ];

    $backend_fields = [
      'search_api_id' => TRUE,
      'search_api_language' => TRUE,
    ];

    if (empty($condition_group->getConditions())) {
      return $filters;
    }

    $conjunction = $condition_group->getConjunction();

    foreach ($condition_group->getConditions() as $condition) {
      $filter = NULL;

      // Simple filter [field_id, value, operator].
      if ($condition instanceof Condition) {
        $field_id = (string) $condition->getField();
        $operator = (string) $condition->getOperator();

        if ($field_id === '' || $operator === '') {
          // @todo When using views the sort field is coming as a filter and
          // messing with this section.
          $this->logger->warning("Invalid condition %condition", ['%condition' => $condition]);
        }

        if (!isset($index_fields[$field_id]) && !isset($backend_fields[$field_id])) {
          throw new SearchApiException(sprintf("Invalid field '%s' in search filter", $field_id));
        }

        // Check operator.
        if ($operator === '') {
          throw new SearchApiException(sprintf('Unspecified filter operator for field "%s"', $field_id));
        }

        // For some data type, we need to do conversions here.
        if (isset($index_fields[$field_id])) {
          $field = $index_fields[$field_id];
          if ($field->getType() === 'boolean') {
            $condition->setValue((bool) $condition->getValue());
          }

          $index_field_name = $field->getFieldIdentifier();
          // Use the property path if available.
          if (!empty($index_field_name)) {
            // Converting colon notation to Elasticsearch's dot notation.
            $field_id = str_replace(':', '.', $index_field_name);
            $condition->setField($field_id);
          }

          // For text fields in facet filters, append .keyword to use the
          // keyword subfield for exact matching. Text fields are analyzed
          // and cannot be used for exact term queries.
          if ($condition_group->hasTag(sprintf('facet:%s', $field_id)) &&
              $conjunction == "OR" &&
              $field->getType() === 'text') {
            $condition->setField($field_id . '.keyword');
          }
        }

        // Builder filter term.
        $filter = $this->buildFilterTerm($condition, $index_fields, $querySettings);

        if (!empty($filter)) {
          if ($condition_group->hasTag(sprintf('facet:%s', $field_id))
            && $conjunction == "OR"
          ) {
            $filters["post_filters"][] = $filter;

            // For facets_post_filters, use the field name with .keyword.
            $facet_filter_field = $condition->getField();

            if (isset($filters["facets_post_filters"][$field_id])) {
              $existing_filter = $filters["facets_post_filters"][$field_id];
              $merged_values = array_merge(
                $existing_filter['terms'][$facet_filter_field] ?? [],
                (array) $condition->getValue()
              );

              $filters["facets_post_filters"][$field_id] = [
                'terms' => [
                  $facet_filter_field => array_unique($merged_values),
                ],
              ];
            }
            else {
              $filters["facets_post_filters"][$field_id] = [
                'terms' => [
                  $facet_filter_field => (array) $condition->getValue(),
                ],
              ];
            }
          }
          else {
            $filters["filters"][] = $filter;
          }
        }
      }
      // Nested filters.
      elseif ($condition instanceof ConditionGroupInterface) {
        $nested_filters = $this->buildFilters(
          $condition,
          $index_fields,
          $querySettings
        );

        foreach ([
          "filters",
          "post_filters",
        ] as $filter_type) {
          if (!empty($nested_filters[$filter_type])) {
            $filters[$filter_type][] = $nested_filters[$filter_type];
          }
        }

        // Adding back facets_post_filters.
        foreach ($nested_filters["facets_post_filters"] as $facetId => $facetsPostFilters) {
          $filters["facets_post_filters"][$facetId] = $facetsPostFilters;
        }
      }
    }

    foreach ([
      "filters",
      "post_filters",
    ] as $filter_type) {
      // If we have more than 1 filter, we need to nest with a conjunction.
      if (count($filters[$filter_type]) > 1) {
        $filters[$filter_type] = $this->wrapWithConjunction($filters[$filter_type], $conjunction);
      }
      else {
        // Return just the filter.
        $filters[$filter_type] = array_pop($filters[$filter_type]);
      }
    }

    return $filters;
  }

  /**
   * Build a filter term from a Search API condition.
   *
   * @param \Drupal\search_api\Query\Condition $condition
   *   The condition.
   * @param \Drupal\search_api\Item\FieldInterface[] $index_fields
   *   An array of all indexed fields for the index, keyed by field identifier.
   * @param array{prefix?: string, suffix?: string, fuzziness?: string} $querySettings
   *   The query settings.
   *
   * @return array
   *   The filter term array.
   *
   * @throws \Exception
   */
  public function buildFilterTerm(Condition $condition, array $index_fields = [], array $querySettings = []) {
    // Handles "empty", "not empty" operators. Note that Views uses "!=" to mean
    // "not equals"; while Search API uses "<>" to mean "not equals". We cannot
    // guarantee that
    // \Drupal\search_api\Plugin\views\query\SearchApiQuery::sanitizeOperator()
    // has run, and we can't run it ourselves, because it is a protected method,
    // so match BOTH '<>' and '!=' below.
    if (is_null($condition->getValue())) {
      return match ($condition->getOperator()) {
        '<>', '!=' => ['exists' => ['field' => $condition->getField()]],
        '=' => ['bool' => ['must_not' => ['exists' => ['field' => $condition->getField()]]]],
        default => throw new SearchApiException(sprintf('Invalid condition for field %s', $condition->getField())),
      };
    }

    // Normal filters.
    // Once again, we match both '<>', '!=' to mean "not equals", see above.
    $filter = match ($condition->getOperator()) {
      '=' => [
        'term' => [$condition->getField() => $this->getStringValueElasticBool($condition->getValue())],
      ],
      'IN' => [
        'terms' => [$condition->getField() => array_values($condition->getValue())],
      ],
      'NOT IN' => [
        'bool' => ['must_not' => ['terms' => [$condition->getField() => array_values($condition->getValue())]]],
      ],
      '<>', '!=' => [
        'bool' => ['must_not' => ['term' => [$condition->getField() => $this->getStringValueElasticBool($condition->getValue())]]],
      ],
      'LIKE' => [
        'match' => [
          $condition->getField() => [
            'query' => $condition->getValue(),
            'fuzziness' => $this->getQueryFuzziness($querySettings),
          ],
        ],
      ],
      'NOT LIKE' => [
        'bool' => [
          'must_not' => [
            'match' => [
              $condition->getField() => [
                'query' => $condition->getValue(),
                'fuzziness' => $this->getQueryFuzziness($querySettings),
              ],
            ],
          ],
        ],
      ],
      'EXACT' => [
        'match_phrase' => [
          $condition->getField() => ['query' => $condition->getValue()],
        ],
      ],
      '>', '>=', '<', '<=', 'BETWEEN', 'NOT BETWEEN' => $this->getRangeFilter($condition, $index_fields),
      default => throw new SearchApiException(sprintf('Undefined operator "%s" for field "%s" in filter condition.', $condition->getOperator(), $condition->getField())),
    };
    return $filter;
  }

  /**
   * Convert value to string, following Elastic API conventions for booleans.
   *
   * @param mixed $input
   *   The value to convert to a string.
   *
   * @return string
   *   The input value casted to a string. If the input value is a boolean, this
   *   will return the string 'true' or 'false'.
   *
   * @see https://www.elastic.co/docs/reference/elasticsearch/rest-apis/api-conventions#_boolean_values
   */
  protected function getStringValueElasticBool(mixed $input): string {
    // Convert booleans to 'true' or 'false'.
    if (\is_bool($input)) {
      return match ($input) {
        TRUE => 'true',
        FALSE => 'false',
      };
    }

    // Convert everything else.
    return (string) $input;
  }

  /**
   * Get the current query's default fuzziness.
   *
   * @param array{prefix?: string, suffix?: string, fuzziness?: string} $querySettings
   *   The query settings.
   *
   * @return string
   *   An Elasticsearch fuzziness parameter, to allow for inexact matches.
   *
   * @see https://www.elastic.co/docs/reference/elasticsearch/rest-apis/common-options#fuzziness
   * @see \Drupal\elasticsearch_connector\Plugin\search_api\backend\ElasticSearchBackend
   */
  protected function getQueryFuzziness(array $querySettings = []): string {
    return $querySettings['fuzziness'] ?? ElasticSearchBackend::FUZZINESS_AUTO;
  }

  /**
   * Get the filter for range query.
   *
   * @param \Drupal\search_api\Query\Condition $condition
   *   The condition.
   * @param \Drupal\search_api\Item\FieldInterface[] $index_fields
   *   An array of all indexed fields for the index, keyed by field identifier.
   *
   * @return array
   *   The filter term array.
   */
  protected function getRangeFilter(Condition $condition, array $index_fields) : array {
    $isNeg = FALSE;
    $field = $index_fields[$condition->getField()] ?? NULL;

    $field_type = $field?->getType() ?? '';

    $rangeOption = [];

    $value = $condition->getValue();
    switch ($condition->getOperator()) {
      case '>=':
        $rangeOption["gte"] = $value;
        break;

      case '>':
        $rangeOption["gt"] = $value;
        break;

      case '<=':
        $rangeOption["lte"] = $value;
        break;

      case '<':
        $rangeOption["lt"] = $value;
        break;

      case 'NOT BETWEEN':
        // Note that this case intentionally falls through to the next one.
        $isNeg = TRUE;

      case 'BETWEEN':
        $rangeOption["gte"] = $value[0] ?? NULL;
        $rangeOption["lte"] = $value[1] ?? NULL;
        break;
    }

    // If all the values are integers, then we can assume that the values are
    // UNIX timestamps, i.e.: epoch_second format.
    // @see https://www.elastic.co/docs/reference/elasticsearch/mapping-reference/mapping-date-format#:~:text=epoch_second
    $allInt = is_int($value)
      || (
        is_array($value)
        && (isset($value[0]) && is_int($value[0]))
        && (isset($value[1]) && is_int($value[1]))
      );
    if ($field_type == "date" && $allInt) {
      $rangeOption["format"] = "epoch_second";
    }

    $filter = [
      'range' => [
        $condition->getField() => $rangeOption,
      ],
    ];

    if ($isNeg) {
      $filter = [
        'bool' => [
          'must_not' => $filter,
        ],
      ];
    }

    return $filter;
  }

  /**
   * Wraps filters with the conjunction.
   *
   * @param array $filters
   *   Array of filter parameters.
   * @param string $conjunction
   *   The conjunction used by the corresponding Search API condition group –
   *   either 'AND' or 'OR'.
   *
   * @return array
   *   Returns the passed $filters array wrapped in an array keyed by 'should'
   *   or 'must', as appropriate, based on the given conjunction.
   *
   * @throws \Drupal\search_api\SearchApiException
   *   Thrown if there is an invalid conjunction.
   */
  protected function wrapWithConjunction(array $filters, string $conjunction) {
    $f = match ($conjunction) {
      "OR" => ['should' => $filters],
      "AND" => ['must' => $filters],
      default => throw new SearchApiException(sprintf('Unknown filter conjunction "%s". Valid values are "OR" or "AND"', $conjunction)),
    };
    return ['bool' => $f];
  }

}
