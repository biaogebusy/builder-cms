<?php

namespace Drupal\page_manager\Plugin;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Plugin manager for Page Manager menu types.
 */
class PageManagerMenuManager extends DefaultPluginManager {

  /**
   * Constructs a PageManagerMenuManager.
   *
   * @param \Traversable $namespaces
   *   The namespaces.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   The cache backend.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    $this->alterInfo('page_manager_menu_info');
    $this->setCacheBackend($cache_backend, 'page_manager_menu_plugins');
    parent::__construct('Plugin/PageManagerMenu', $namespaces, $module_handler, 'Drupal\page_manager\Plugin\PageManagerMenuInterface', 'Drupal\page_manager\Annotation\PageManagerMenu');
  }

}
