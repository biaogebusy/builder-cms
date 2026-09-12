<?php

namespace Drupal\elasticsearch_connector\SearchAPI\Query;

use Drupal\search_api\Item\FieldInterface;
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
      $aggs += $this->buildTermBucketAgg($query, $facet_id, $facet, $facetFilters, $indexFields[$field]);
    }

    return $aggs;
  }

  /**
   * Builds a bucket aggregation.
   *
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   Search API query.
   * @param string $facet_id
   *   The key.
   * @param array $facet
   *   The facet.
   * @param array $postFilter
   *   The filter for the facets.
   * @param \Drupal\search_api\Item\FieldInterface|null $indexField
   *   The index field.
   *
   * @return array
   *   The bucket aggregation.
   */
  protected function buildTermBucketAgg(QueryInterface $query, string $facet_id, array $facet, array $postFilter, ?FieldInterface $indexField = NULL): array {
    $fieldName = $facet['field'];
    if ($indexField) {
      $fieldName = str_replace(':', '.', $indexField->getPropertyPath());
      $facet_id = $fieldName;
    }

    // Get fulltext fields from the index.
    $query_full_text_fields = $query->getIndex()->getFulltextFields();

    // For text/fulltext fields, use the .keyword subfield for aggregations.
    // Elasticsearch text fields are analyzed/tokenized, making them unsuitable
    // for aggregations. By default, Elasticsearch creates a .keyword subfield
    // (type: keyword) which stores the exact, non-analyzed value.
    // Attempting to aggregate on a text field will fail with the error:
    // "Fielddata is disabled on [field]. Text fields are not optimized for
    // operations that require per-document field data like aggregations...".
    // @see https://www.elastic.co/guide/en/elasticsearch/reference/current/search-aggregations-bucket-terms-aggregation.html#search-aggregations-bucket-terms-aggregation-text
    if (in_array($facet['field'], $query_full_text_fields)) {
      $fieldName .= '.keyword';
    }

    $agg = [
      $facet_id => ["terms" => ["field" => $fieldName]],
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

    $condition = $query->getConditionGroup()->getConjunction();
    switch (\strtolower($condition)) {
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
