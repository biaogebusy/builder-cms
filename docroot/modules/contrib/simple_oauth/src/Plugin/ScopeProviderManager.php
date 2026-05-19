<?php

namespace Drupal\simple_oauth\Plugin;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Provides the Scope Provider plugin manager.
 */
class ScopeProviderManager extends DefaultPluginManager implements ScopeProviderManagerInterface {

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
    parent::__construct(
      'Plugin/ScopeProvider',
      $namespaces, $module_handler,
      'Drupal\simple_oauth\Plugin\ScopeProviderInterface',
      'Drupal\simple_oauth\Annotation\ScopeProvider'
    );

    $this->alterInfo('simple_oauth_scope_provider_info');
    $this->setCacheBackend($cache_backend, 'simple_oauth_scope_provider_plugins');
  }

  /**
   * {@inheritdoc}
   */
  public function getInstance(array $options) {
    $plugin_id = $options['id'];

    if (!isset($this->instances[$plugin_id])) {
      $this->instances[$plugin_id] = $this->createInstance($plugin_id);
    }

    return $this->instances[$plugin_id];
  }

  /**
   * {@inheritdoc}
   */
  public function getInstances(): array {
    foreach (array_keys($this->getDefinitions()) as $plugin_id) {
      if (!isset($this->instances[$plugin_id])) {
        $this->instances[$plugin_id] = $this->createInstance($plugin_id);
      }
    }

    return $this->instances;
  }

}
