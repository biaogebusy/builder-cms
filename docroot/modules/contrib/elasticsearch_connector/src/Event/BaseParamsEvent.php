<?php

namespace Drupal\elasticsearch_connector\Event;

use Drupal\Component\EventDispatcher\Event;

/**
 * Provides a base class for events that alter params.
 */
abstract class BaseParamsEvent extends Event {

  /**
   * The index name.
   *
   * @var string
   */
  protected $indexName;

  /**
   * The ElasticSearch build params.
   *
   * @var array
   */
  protected $params;

  /**
   * BuildSearchParamsEvent constructor.
   *
   * @param string $indexName
   *   The index name.
   * @param array $params
   *   The params.
   */
  public function __construct(string $indexName, array $params) {
    $this->indexName = $indexName;
    $this->params = $params;
  }

  /**
   * Gets the params.
   *
   * @return array
   *   The params.
   */
  public function getParams(): array {
    return $this->params;
  }

  /**
   * Sets the params.
   *
   * @param array $params
   *   The params.
   */
  public function setParams(array $params): void {
    $this->params = $params;
  }

  /**
   * Gets the index name.
   *
   * @return string
   *   The index name.
   */
  public function getIndexName(): string {
    return $this->indexName;
  }

}
