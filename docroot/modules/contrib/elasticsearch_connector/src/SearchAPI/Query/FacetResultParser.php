<?php

namespace Drupal\elasticsearch_connector\SearchAPI\Query;

use Drupal\search_api\Query\QueryInterface;
use Psr\Log\LoggerInterface;

/**
 * Provides a facet result parser.
 */
class FacetResultParser {

  /**
   * Creates a new facet result parser.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(
    protected LoggerInterface $logger,
  ) {
  }

  /**
   * Parse the facet result.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The query.
   * @param array $response
   *   The response.
   *
   * @return array
   *   The facet data in the format expected by facets module.
   */
  public function parseFacetResult(QueryInterface $query, array $response): array {
    $facetData = [];
    $indexFields = $query->getIndex()->getFields();
    $facets = $query->getOption('search_api_facets', []);

    foreach ($facets as $facet_id => $facet) {
      $index_path = $facet_id;
      if (isset($indexFields[$facet_id])) {
        $index_path = $indexFields[$facet_id]->getPropertyPath();
        $index_path = str_replace(':', '.', $index_path);
      }

      $filtered_facet_id = \sprintf('%s_filtered', $index_path);

      $buckets = isset($response['aggregations'][$filtered_facet_id])
        ? ($response['aggregations'][$filtered_facet_id][$index_path]['buckets'] ?? [])
        : ($response['aggregations'][$index_path]['buckets'] ?? []);

      $facetData[$facet_id] = \array_map(function (array $value): array {
        // If the key is not set, or the key is the empty string, return an
        // exclamation mark; otherwise return the key in double quotes.
        return [
          'count' => $value['doc_count'] ?? 0,
          'filter' => (!isset($value['key']) || $value['key'] === '') ? '!' : \sprintf('"%s"', $value['key']),
        ];
      }, $buckets);

    }
    return $facetData;
  }

}
