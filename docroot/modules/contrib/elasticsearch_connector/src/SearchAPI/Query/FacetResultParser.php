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
    $facets = $query->getOption('search_api_facets', []);

    foreach ($facets as $facet_id => $facet) {

      $filtered_facet_id = \sprintf('%s_filtered', $facet_id);

      $buckets = isset($response['aggregations'][$filtered_facet_id])
        ? ($response['aggregations'][$filtered_facet_id][$facet_id]['buckets'] ?? [])
        : ($response['aggregations'][$facet_id]['buckets'] ?? []);

      $facetData[$facet_id] = \array_map(function (array $value): array {
        return [
          'count' => $value['doc_count'] ?? 0,
          'filter' => empty($value['key']) ? '!' : \sprintf('"%s"', $value['key']),
        ];
      }, $buckets);

    }
    return $facetData;
  }

}
