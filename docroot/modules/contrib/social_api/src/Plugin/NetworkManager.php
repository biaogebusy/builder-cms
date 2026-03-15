<?php

namespace Drupal\social_api\Plugin;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Provides the Social Network plugin manager.
 */
class NetworkManager extends DefaultPluginManager {

  /**
   * Constructor for NetworkManager objects.
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
    parent::__construct('Plugin/Network', $namespaces, $module_handler, 'Drupal\social_api\Plugin\NetworkInterface', 'Drupal\social_api\Annotation\Network');

    $this->alterInfo('social_api_network_info');
    $this->setCacheBackend($cache_backend, 'social_api_network_plugins');
  }

  /**
   * Gets the module handler.
   *
   * @return \Drupal\Core\Extension\ModuleHandlerInterface
   *   Module handler.
   */
  public function getModuleHandler(): ModuleHandlerInterface {
    return $this->moduleHandler;
  }

}
