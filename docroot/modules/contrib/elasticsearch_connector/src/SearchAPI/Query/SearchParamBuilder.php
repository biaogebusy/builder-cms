<?php

namespace Drupal\elasticsearch_connector\SearchAPI\Query;

use Drupal\search_api\Query\QueryInterface;
use MakinaCorpus\Lucene\Query;
use MakinaCorpus\Lucene\TermQuery;

/**
 * Provides a search param builder.
 */
class SearchParamBuilder {

  /**
   * Builds the search params for the query.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The query.
   * @param \Drupal\search_api\Item\FieldInterface[] $indexFields
   *   The index fields.
   * @param array $settings
   *   The settings.
   *
   * @return array
   *   An associative array with keys:
   *   - query: the search string
   *   - fields: the query fields
   *
   * @throws \Drupal\search_api\SearchApiException
   *   Thrown if there is an underlying Search API error.
   */
  public function buildSearchParams(QueryInterface $query, array $indexFields, array $settings): array {
    $index = $query->getIndex();
    // Full text search.
    $keys = $query->getKeys();

    if (empty($keys)) {
      return [];
    }

    // Ensure $keys are an array.
    if (is_string($keys)) {
      $keys = [$keys];
    }

    // Get the fulltext fields to search on.
    $fulltextFieldIds = $query->getFulltextFields();
    if (!empty($fulltextFieldIds)) {
      // Make sure the fields exists within the indexed fields.
      $fulltextFieldIds = array_intersect($index->getFulltextFields(), $fulltextFieldIds);
    }
    else {
      // Default to all index fulltext fields.
      $fulltextFieldIds = $index->getFulltextFields();
    }

    $queryFields = [];
    foreach ($fulltextFieldIds as $fieldId) {
      $field = $indexFields[$fieldId];
      $queryFields[] = $field->getFieldIdentifier() . '^' . $field->getBoost();
    }

    $params = [];
    $parse_mode_id = $query->getParseMode()->getPluginId();
    switch ($parse_mode_id) {
      case 'direct':
        // Multi-match query (suitable for full-text search).
        // https://www.elastic.co/docs/reference/query-languages/query-dsl/query-dsl-multi-match-query
        $params['multi_match']['query'] = $query->getOriginalKeys();
        $params['multi_match']['fields'] = $queryFields;
        break;

      default:
        // Query string query (expects a query that follows the Lucene syntax).
        // https://www.elastic.co/docs/reference/query-languages/query-dsl/query-dsl-query-string-query
        $luceneQuery = $this->buildSearchString($keys, $settings['fuzziness']);
        if (!$luceneQuery->isEmpty()) {
          $params['query_string']['query'] = (string) $luceneQuery;
          $params['query_string']['fields'] = $queryFields;
        }
    }

    return $params;

  }

  /**
   * Builds the search string.
   *
   * @param array $keys
   *   Search keys, in the format described by
   *   \Drupal\search_api\ParseMode\ParseModeInterface::parseInput().
   * @param string|null $fuzziness
   *   (optional) The fuzziness. Defaults to "auto".
   *
   * @return \MakinaCorpus\Lucene\Query
   *   The lucene query.
   */
  public function buildSearchString(array $keys, ?string $fuzziness = "auto"): Query {
    $conjunction = $keys['#conjunction'] ?? Query::OP_OR;
    $negation = !empty($keys['#negation']);

    // Filter out top level properties beginning with '#'.
    $keys = array_filter($keys, function (string $key) {
      return $key[0] !== '#';
    }, ARRAY_FILTER_USE_KEY);

    // Create a CollectionQuery with the above values.
    $query = (new Query())->setOperator($conjunction);
    if ($negation) {
      $query->setExclusion(Query::OP_PROHIBIT);
    }

    // Add a TermQuery for each key, recurse on arrays.
    foreach ($keys as $key) {
      $termQuery = NULL;

      if (is_array($key)) {
        $termQuery = $this->buildSearchString($key, $fuzziness);
      }
      elseif (is_string($key)) {
        $termQuery = (new TermQuery())->setValue($key);
        if (!empty($fuzziness)) {
          $termQuery->setFuzzyness($fuzziness);
        }
      }

      if (!empty($termQuery)) {
        $query->add($termQuery);
      }
    }
    return $query;
  }

}
