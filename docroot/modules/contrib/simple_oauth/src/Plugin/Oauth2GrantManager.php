<?php

namespace Drupal\simple_oauth\Plugin;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Provides the OAuth2 Grant plugin manager.
 */
class Oauth2GrantManager extends DefaultPluginManager implements Oauth2GrantManagerInterface {

  /**
   * The plugin instances.
   *
   * @var array
   */
  protected array $instances = [];

  /**
   * Constructor for Oauth2GrantManager objects.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   *
   * @throws \Exception
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/Oauth2Grant', $namespaces, $module_handler, 'Drupal\simple_oauth\Plugin\Oauth2GrantInterface', 'Drupal\simple_oauth\Annotation\Oauth2Grant');

    $this->alterInfo('simple_oauth_oauth2_grant_info');
    $this->setCacheBackend($cache_backend, 'simple_oauth_oauth2_grant_plugins');
  }

  /**
   * {@inheritdoc}
   */
  public function getInstances(?array $ids = NULL): array {
    $instances = [];

    if (empty($ids)) {
      $ids = array_keys($this->getDefinitions());
    }

    foreach ($ids as $plugin_id) {
      if (!isset($this->instances[$plugin_id])) {
        $this->instances[$plugin_id] = $this->createInstance($plugin_id);
      }
      $instances[$plugin_id] = $this->instances[$plugin_id];
    }

    return $instances;
  }

  /**
   * Get the available plugins as form element options.
   *
   * @return array
   *   Returns the options.
   */
  public static function getAvailablePluginsAsOptions(): array {
    /** @var \Drupal\simple_oauth\Plugin\Oauth2GrantManagerInterface $plugin_manager */
    $plugin_manager = \Drupal::service('plugin.manager.oauth2_grant.processor');
    $options = [];
    foreach ($plugin_manager->getDefinitions() as $plugin_id => $definition) {
      $options[$plugin_id] = $definition['label'];
    }

    return $options;
  }

}
