<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\ClientAuthorization;

use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Provides the Client authorization plugin manager.
 */
class ClientAuthorizationPluginManager extends DefaultPluginManager {

  /**
   * Constructs a new ClientAuthorizationManager object.
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
    parent::__construct('Plugin/ClientAuthorization', $namespaces, $module_handler, 'Drupal\entity_share_client\ClientAuthorization\ClientAuthorizationInterface', 'Drupal\entity_share_client\Annotation\ClientAuthorization');

    $this->alterInfo('entity_share_client_authorization_info');
    $this->setCacheBackend($cache_backend, 'entity_share_client_authorization_plugins');
  }

  /**
   * Builds an array of currently available plugin instances.
   *
   * @param string $uuid
   *   Allow the uuid to be explicitly set.
   *
   * @return \Drupal\entity_share_client\ClientAuthorization\ClientAuthorizationInterface[]
   *   The array of plugins.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function getAvailablePlugins($uuid = '') {
    $plugins = [];
    $configuration = empty($uuid) ? [] : ['uuid' => $uuid];
    $definitions = $this->getDefinitions();
    foreach ($definitions as $definition) {
      /** @var \Drupal\entity_share_client\ClientAuthorization\ClientAuthorizationInterface $plugin */
      $plugin = $this->createInstance($definition['id'], $configuration);
      if ($plugin->checkIfAvailable()) {
        $plugins[$plugin->getPluginId()] = $plugin;
      }
    }
    return $plugins;
  }

}
