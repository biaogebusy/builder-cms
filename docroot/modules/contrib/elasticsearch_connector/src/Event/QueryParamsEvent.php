<?php

namespace Drupal\elasticsearch_connector\Event;

use Drupal\search_api\Query\QueryInterface;

/**
 * Event triggered when search params are built.
 */
class QueryParamsEvent extends BaseParamsEvent {

  /**
   * Search Query Object.
   *
   * @var \Drupal\search_api\Query\QueryInterface
   */
  protected QueryInterface $query;

  /**
   * BuildSearchParamsEvent constructor.
   *
   * @param string $indexName
   *   The index name.
   * @param array $params
   *   The params.
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The query object.
   */
  public function __construct(string $indexName, array $params, QueryInterface $query) {
    parent::__construct($indexName, $params);
    $this->query = $query;
  }

  /**
   * Get the settings.
   */
  public function getQuery(): QueryInterface {
    return $this->query;
  }

}
