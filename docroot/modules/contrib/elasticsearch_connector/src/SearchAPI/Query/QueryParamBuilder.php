<?php

namespace Drupal\elasticsearch_connector\SearchAPI\Query;

use Drupal\elasticsearch_connector\Event\QueryParamsEvent;
use Drupal\elasticsearch_connector\SearchAPI\MoreLikeThisParamBuilder;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Query\QueryInterface;
use Drupal\search_api\Utility\FieldsHelperInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Provides a query param builder for search operations.
 */
class QueryParamBuilder {

  /**
   * The default query offset.
   */
  const DEFAULT_OFFSET = 0;

  /**
   * The default query limit.
   */
  const DEFAULT_LIMIT = 10;

  /**
   * Creates a new QueryParamBuilder.
   *
   * @param \Drupal\search_api\Utility\FieldsHelperInterface $fieldsHelper
   *   The fields helper.
   * @param \Drupal\elasticsearch_connector\SearchAPI\Query\QuerySortBuilder $sortBuilder
   *   The sort builder.
   * @param \Drupal\elasticsearch_connector\SearchAPI\Query\FilterBuilder $filterBuilder
   *   The filter builder.
   * @param \Drupal\elasticsearch_connector\SearchAPI\Query\SearchParamBuilder $searchParamBuilder
   *   The search param builder.
   * @param \Drupal\elasticsearch_connector\SearchAPI\MoreLikeThisParamBuilder $mltParamBuilder
   *   The More Like This param builder.
   * @param \Drupal\elasticsearch_connector\SearchAPI\Query\FacetParamBuilder $facetBuilder
   *   The facet param builder.
   * @param \Drupal\elasticsearch_connector\SearchAPI\Query\SpellCheckBuilder $spellCheckBuilder
   *   The spell check query builder.
   * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   * @param \Psr\Log\LoggerInterface $logger
   *   The logger.
   */
  public function __construct(
    protected FieldsHelperInterface $fieldsHelper,
    protected QuerySortBuilder $sortBuilder,
    protected FilterBuilder $filterBuilder,
    protected SearchParamBuilder $searchParamBuilder,
    protected MoreLikeThisParamBuilder $mltParamBuilder,
    protected FacetParamBuilder $facetBuilder,
    protected SpellCheckBuilder $spellCheckBuilder,
    protected EventDispatcherInterface $eventDispatcher,
    protected LoggerInterface $logger,
  ) {
  }

  /**
   * Build up the body of the request to the ElasticSearch _search endpoint.
   *
   * @param string $indexId
   *   The query.
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The index ID.
   * @param array $settings
   *   The query settings.
   *
   * @return array
   *   Array or parameters to send along to the ElasticSearch _search endpoint.
   *
   * @throws \Drupal\search_api\SearchApiException
   *   Thrown if an error occurs building query params.
   */
  public function buildQueryParams(string $indexId, QueryInterface $query, array $settings): array {
    $index = $query->getIndex();
    $params = [
      'index' => $indexId,
    ];

    $body = [];

    // Set the size and from parameters.
    $body['from'] = $query->getOption('offset') ?? self::DEFAULT_OFFSET;
    $body['size'] = $query->getOption('limit') ?? self::DEFAULT_LIMIT;

    // Sort.
    $sort = $this->sortBuilder->getSortSearchQuery($query);
    if (!empty($sort)) {
      $body['sort'] = $sort;
    }

    $languages = $query->getLanguages();
    if ($languages !== NULL) {
      $query->getConditionGroup()->addCondition('search_api_language', $languages, 'IN');
    }

    $index_fields = $this->getIndexFields($index);

    // Filters.
    $filters = $this->filterBuilder->buildFilters($query->getConditionGroup(), $index_fields);

    // Build the query.
    $searchParams = $this->searchParamBuilder->buildSearchParams($query, $index_fields, $settings);
    if (!empty($searchParams) && !empty($filters["filters"])) {
      $body['query']['bool']['must'] = $searchParams;
      $body['query']['bool']['filter'] = $filters["filters"];
    }
    elseif (!empty($searchParams)) {
      if (empty($body['query'])) {
        $body['query'] = [];
      }
      $body['query'] += $searchParams;
    }
    elseif (!empty($filters["filters"])) {
      $body['query']['bool']['filter'] = $filters["filters"];
    }

    // @todo Handle fields on filter query.
    if (isset($body['fields']) && empty($body['fields'])) {
      unset($body['fields']);
    }

    if (!empty($filters["post_filters"])) {
      $body['post_filter']['bool']['must'] = $filters["post_filters"];
    }

    if (isset($body['post_filter']) && empty($body['post_filter'])) {
      unset($body['post_filter']);
    }

    // If the body is empty, match all.
    if (empty($body)) {
      $body['match_all'] = [];
    }

    $exclude_source_fields = $query->getOption('elasticsearch_exclude_source_fields', []);
    if (!empty($exclude_source_fields)) {
      $body['_source'] = [
        'excludes' => $exclude_source_fields,
      ];
    }

    // More Like This.
    if (!empty($query->getOption('search_api_mlt'))) {
      $body['query']['bool']['must'][] = $this->mltParamBuilder->buildMoreLikeThisQuery($query->getOption('search_api_mlt'), $index);
    }

    if (!empty($query->getOption('search_api_facets'))) {
      $aggs = $this->facetBuilder->buildFacetParams($query, $index_fields, $filters["facets_post_filters"]);
      if (!empty($aggs)) {
        $body['aggs'] = $aggs;
      }
    }

    // Spellcheck.
    if (!empty($query->getOption('search_api_spellcheck'))) {
      $suggest = $this->spellCheckBuilder->setSpellCheckQuery($query);
      if (!empty($suggest)) {
        $body['suggest'] = $suggest;
      }
    }

    // Add highlighting configuration, if configured.
    if (!empty($query->getOption('highlight'))) {
      $body['highlight'] = $query->getOption('highlight');
    }

    $params['body'] = $body;
    // Preserve the options for further manipulation if necessary.
    $query->setOption('ElasticSearchParams', $params);

    // Allow modification of search params via an event.
    $event = new QueryParamsEvent($indexId, $params);
    $this->eventDispatcher->dispatch($event);
    $params = $event->getParams();

    return $params;
  }

  /**
   * Gets the list of index fields.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The index.
   *
   * @return \Drupal\search_api\Item\FieldInterface[]
   *   The index fields, keyed by field identifier.
   */
  protected function getIndexFields(IndexInterface $index): array {
    $index_fields = $index->getFields();

    // Search API does not provide metadata for some special fields but might
    // try to query for them. Thus add the metadata so we allow for querying
    // them.
    if (empty($index_fields['search_api_datasource'])) {
      $index_fields['search_api_datasource'] = $this->fieldsHelper->createField($index, 'search_api_datasource', ['type' => 'string']);
    }
    if (empty($index_fields['search_api_id'])) {
      $index_fields['search_api_id'] = $this->fieldsHelper->createField($index, 'search_api_id', ['type' => 'string']);
    }
    return $index_fields;
  }

}
