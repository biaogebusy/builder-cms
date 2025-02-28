<?php

namespace Drupal\bootstrap_styles\Style;

use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Provides an Style plugin manager.
 */
class StyleManager extends DefaultPluginManager implements StylePluginManagerInterface {

  /**
   * Constructs a StyleManager object.
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
    parent::__construct(
      'Plugin/BootstrapStyles/Style',
      $namespaces,
      $module_handler,
      'Drupal\bootstrap_styles\Style\StylePluginInterface',
      'Drupal\bootstrap_styles\Annotation\Style'
    );
    $this->alterInfo('bootstrap_styles_info');
    $this->setCacheBackend($cache_backend, 'bootstrap_styles_styles');
  }

}
