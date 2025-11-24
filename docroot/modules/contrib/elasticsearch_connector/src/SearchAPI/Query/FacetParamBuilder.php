<?php

namespace Drupal\elasticsearch_connector\SearchAPI\Query;

use Drupal\search_api\Query\QueryInterface;
use Psr\Log\LoggerInterface;

/**
 * Builds facet params.
 */
class FacetParamBuilder {

  /**
   * The default facet size.
   */
  protected const DEFAULT_FACET_SIZE = 10;

  /**
   * The unlimited facet size.
   */
  protected const UNLIMITED_FACET_SIZE = 10000;

  /**
   * Creates a new Facet builder.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(
    protected LoggerInterface $logger,
  ) {
  }

  /**
   * Fill the aggregation array of the request.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   Search API query.
   * @param array $indexFields
   *   The index field, keyed by field identifier.
   * @param array $facetFilters
   *   The facet filters, keyed by facet identifier.
   *
   * @return array
   *   The facets params.
   */
  public function buildFacetParams(QueryInterface $query, array $indexFields, array $facetFilters = []) {
    $aggs = [];
    $facets = $query->getOption('search_api_facets', []);
    if (empty($facets)) {
      return $aggs;
    }

    foreach ($facets as $facet_id => $facet) {
      $field = $facet['field'];
      if (!isset($indexFields[$field])) {
        $this->logger->warning('Unknown facet field: %field', ['%field' => $field]);
        continue;
      }
      // Default to term bucket aggregation.
      $aggs += $this->buildTermBucketAgg($facet_id, $facet, $facetFilters);
    }

    return $aggs;
  }

  /**
   * Builds a bucket aggregation.
   *
   * @param string $facet_id
   *   The key.
   * @param array $facet
   *   The facet.
   * @param array $postFilter
   *   The filter for the facets.
   *
   * @return array
   *   The bucket aggregation.
   */
  protected function buildTermBucketAgg(string $facet_id, array $facet, array $postFilter): array {
    $agg = [
      $facet_id => ["terms" => ["field" => $facet['field']]],
    ];

    $size = $facet['limit'] ?? self::DEFAULT_FACET_SIZE;
    $size = (int) $size;

    // Facets uses zero in its configuration form to mean 'No limit'.
    if ($size === 0) {
      $size = self::UNLIMITED_FACET_SIZE;
    }

    $agg[$facet_id]["terms"]["size"] = $size;

    if ($facet['missing'] ?? FALSE) {
      $agg[$facet_id]["terms"]["missing"] = "";
    }

    if (isset($facet['min_count'])) {
      $agg[$facet_id]["terms"]["min_doc_count"] = $facet['min_count'];
    }

    if (empty($postFilter)) {
      return $agg;
    }

    $filters = [];

    foreach ($postFilter as $filter_facet_id => $filter) {
      if ($filter_facet_id == $facet_id && $facet['operator'] === 'or') {
        continue;
      }
      $filters[] = $filter;
    }

    if (empty($filters)) {
      return $agg;
    }

    if (count($filters) == 1) {
      $filters = array_pop($filters);
    }

    $filtered_facet_id = \sprintf('%s_filtered', $facet_id);

    switch ($facet['operator']) {
      case 'or':
        $facet_operator = 'should';
        break;

      case 'and':
      default:
        $facet_operator = 'must';
        break;
    }

    $agg = [
      $filtered_facet_id => [
        'filter' => [
          'bool' => [
            $facet_operator => $filters,
          ],
        ],
        'aggs' => $agg,
      ],
    ];

    return $agg;
  }

}
