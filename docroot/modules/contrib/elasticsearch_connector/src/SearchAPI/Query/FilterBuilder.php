<?php

namespace Drupal\elasticsearch_connector\SearchAPI\Query;

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
   *
   * @return array
   *   Array of filter parameters to apply to query based on the given Search
   *   API condition group.
   *
   * @throws \Drupal\search_api\SearchApiException
   *   Thrown if an invalid condition occurs.
   */
  public function buildFilters(ConditionGroupInterface $condition_group, array $index_fields) {

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
        if (!$condition->getField() || !$condition->getValue() || !$condition->getOperator()) {
          // @todo When using views the sort field is coming as a filter and
          // messing with this section.
          $this->logger->warning("Invalid condition %condition", ['%condition' => $condition]);
        }

        $field_id = $condition->getField();
        if (!isset($index_fields[$field_id]) && !isset($backend_fields[$field_id])) {
          throw new SearchApiException(sprintf("Invalid field '%s' in search filter", $field_id));
        }

        // Check operator.
        if (!$condition->getOperator()) {
          throw new SearchApiException(sprintf('Unspecified filter operator for field "%s"', $field_id));
        }

        // For some data type, we need to do conversions here.
        if (isset($index_fields[$field_id])) {
          $field = $index_fields[$field_id];
          if ($field->getType() === 'boolean') {
            $condition->setValue((bool) $condition->getValue());
          }
        }

        // Builder filter term.
        $filter = $this->buildFilterTerm($condition, $index_fields);

        if (!empty($filter)) {
          if ($condition_group->hasTag(sprintf('facet:%s', $field_id))
            && $conjunction == "OR"
          ) {
            $filters["post_filters"][] = $filter;
            if (isset($filters["facets_post_filters"][$field_id])) {
              $existing_filter = $filters["facets_post_filters"][$field_id];
              $merged_values = array_merge(
                $existing_filter['terms'][$field_id] ?? [],
                (array) $condition->getValue()
              );

              $filters["facets_post_filters"][$field_id] = [
                'terms' => [
                  $field_id => array_unique($merged_values),
                ],
              ];
            }
            else {
              $filters["facets_post_filters"][$field_id] = [
                'terms' => [
                  $field_id => (array) $condition->getValue(),
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
          $index_fields
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
   *
   * @return array
   *   The filter term array.
   *
   * @throws \Exception
   */
  public function buildFilterTerm(Condition $condition, array $index_fields = []) {
    // Handles "empty", "not empty" operators.
    if (is_null($condition->getValue())) {
      return match ($condition->getOperator()) {
        '<>' => ['exists' => ['field' => $condition->getField()]],
        '=' => ['bool' => ['must_not' => ['exists' => ['field' => $condition->getField()]]]],
        default => throw new SearchApiException(sprintf('Invalid condition for field %s', $condition->getField())),
      };
    }

    // Normal filters.
    $filter = match ($condition->getOperator()) {
      '=' => [
        'term' => [$condition->getField() => $condition->getValue()],
      ],
      'IN' => [
        'terms' => [$condition->getField() => array_values($condition->getValue())],
      ],
      'NOT IN' => [
        'bool' => ['must_not' => ['terms' => [$condition->getField() => array_values($condition->getValue())]]],
      ],
      '<>' => [
        'bool' => ['must_not' => ['term' => [$condition->getField() => $condition->getValue()]]],
      ],
      '>', '>=', '<', '<=', 'BETWEEN', 'NOT BETWEEN' => $this->getRangeFilter($condition, $index_fields),
      default => throw new SearchApiException(sprintf('Undefined operator "%s" for field "%s" in filter condition.', $condition->getOperator(), $condition->getField())),
    };
    return $filter;
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
    $allInt = is_array($value)
      && (isset($value[0]) && is_int($value[0]))
      && (isset($value[1]) && is_int($value[1]));
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
