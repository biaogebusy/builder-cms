<?php

namespace Drupal\replication\Plugin;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\replication\Plugin\ReplicationFilterManagerInterface;

/**
 * {@inheritdoc}
 */
class ReplicationFilterManager extends DefaultPluginManager implements ReplicationFilterManagerInterface {

  /**
   * Constructs a ReplicationFilterManager object.
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
    parent::__construct('Plugin/ReplicationFilter', $namespaces, $module_handler, 'Drupal\replication\Plugin\ReplicationFilterInterface', 'Drupal\replication\Annotation\ReplicationFilter');
    $this->setCacheBackend($cache_backend, 'replication_filters');
  }

}
