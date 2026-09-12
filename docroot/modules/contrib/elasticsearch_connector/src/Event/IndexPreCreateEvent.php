<?php

namespace Drupal\elasticsearch_connector\Event;

use Drupal\search_api\IndexInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Event fired before creating an Elasticsearch index.
 *
 * This event allows modules to modify the index configuration before it's
 * sent to Elasticsearch. This is essential for settings that can only be
 * configured during index creation, such as analysis settings.
 *
 * @see https://www.drupal.org/node/3553011
 */
class IndexPreCreateEvent extends Event {

  /**
   * The index configuration parameters.
   *
   * @var array
   */
  protected array $params;

  /**
   * The Search API index.
   *
   * @var \Drupal\search_api\IndexInterface
   */
  protected IndexInterface $index;

  /**
   * The backend configuration.
   *
   * @var array
   */
  protected array $backendConfig;

  /**
   * Constructs a new IndexPreCreateEvent.
   *
   * @param array $params
   *   The index creation parameters that will be sent to Elasticsearch.
   * @param \Drupal\search_api\IndexInterface $index
   *   The Search API index being created.
   * @param array $backend_config
   *   The backend configuration.
   */
  public function __construct(array $params, IndexInterface $index, array $backend_config = []) {
    $this->params = $params;
    $this->index = $index;
    $this->backendConfig = $backend_config;
  }

  /**
   * Gets the index creation parameters.
   *
   * @return array
   *   The parameters array.
   */
  public function getParams(): array {
    return $this->params;
  }

  /**
   * Sets the index creation parameters.
   *
   * @param array $params
   *   The parameters array.
   */
  public function setParams(array $params): void {
    $this->params = $params;
  }

  /**
   * Gets the Search API index.
   *
   * @return \Drupal\search_api\IndexInterface
   *   The index.
   */
  public function getIndex(): IndexInterface {
    return $this->index;
  }

  /**
   * Gets the backend configuration.
   *
   * @return array
   *   The backend configuration.
   */
  public function getBackendConfig(): array {
    return $this->backendConfig;
  }

}
