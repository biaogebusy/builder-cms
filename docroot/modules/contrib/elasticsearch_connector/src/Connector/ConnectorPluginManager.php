<?php

namespace Drupal\elasticsearch_connector\Connector;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\elasticsearch_connector\Annotation\ElasticSearchConnector;

/**
 * A plugin manager for ElasticSearch connector plugins.
 *
 * @see \Drupal\elasticsearch_connector\Annotation\ElasticSearchConnector
 * @see \Drupal\elasticsearch_connector\Connector\ElasticSearchConnectorInterface
 *
 * @ingroup plugin_api
 */
class ConnectorPluginManager extends DefaultPluginManager {

  /**
   * Constructs a ElasticSearchConnectorManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    $this->alterInfo('elasticsearch_connector_info');
    $this->setCacheBackend($cache_backend, 'elasticsearch_connector_plugins');

    parent::__construct('Plugin/ElasticSearch/Connector', $namespaces, $module_handler, ElasticSearchConnectorInterface::class, ElasticSearchConnector::class);
  }

}
