<?php

namespace Drupal\simple_oauth\Plugin;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\simple_oauth\Attribute\ScopeGranularity;

/**
 * Provides the Scope Granularity plugin manager.
 */
class ScopeGranularityManager extends DefaultPluginManager {

  public function __construct(
    \Traversable $namespaces,
    CacheBackendInterface $cache_backend,
    ModuleHandlerInterface $module_handler,
  ) {
    parent::__construct(
      'Plugin/ScopeGranularity',
      $namespaces,
      $module_handler,
      ScopeGranularityInterface::class,
      ScopeGranularity::class,
    );

    $this->alterInfo('simple_oauth_scope_granularity_info');
    $this->setCacheBackend($cache_backend, 'simple_oauth_scope_granularity_plugins');
  }

  /**
   * Get the available plugins as form element options.
   *
   * @return array
   *   Returns the options.
   */
  public static function getAvailablePluginsAsOptions(): array {
    /** @var \Drupal\Component\Plugin\PluginManagerInterface $plugin_manager */
    $plugin_manager = \Drupal::service('plugin.manager.scope_granularity');
    $options = [];
    foreach ($plugin_manager->getDefinitions() as $plugin_id => $definition) {
      $options[$plugin_id] = $definition['label'];
    }

    return $options;
  }

}
