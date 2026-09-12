<?php

declare(strict_types=1);

namespace Drupal\elasticsearch_connector\Event;

use Drupal\search_api\Query\QueryInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Alter search query parameters before the query is run.
 */
class AlterSearchQueryParamsEvent extends Event {

  /**
   * Creates a new event.
   *
   * @param array $params
   *   The params array to be sent to Elasticsearch.
   * @param \Drupal\search_api\Query\QueryInterface $query
   *   The Search API Query being executed.
   */
  public function __construct(
    protected array $params,
    protected QueryInterface $query,
  ) {}

  /**
   * Get the params array to be sent to Elasticsearch.
   *
   * @return array
   *   The params array to be sent to Elasticsearch.
   */
  public function getParams(): array {
    return $this->params;
  }

  /**
   * Get the Search API Query being executed.
   *
   * @return \Drupal\search_api\Query\QueryInterface
   *   The Search API Query being executed.
   */
  public function getQuery(): QueryInterface {
    return $this->query;
  }

  /**
   * Alter the params array to be sent to Elasticsearch.
   *
   * @param array $params
   *   The params array to be sent to Elasticsearch.
   */
  public function setParams(array $params): void {
    $this->params = $params;
  }

}
