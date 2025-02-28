<?php

namespace Drupal\search_api\Backend;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\search_api\Annotation\SearchApiBackend;
use Drupal\search_api\Event\SearchApiEvents;
use Drupal\search_api\SearchApiPluginManager;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Manages search backend plugins.
 *
 * @see \Drupal\search_api\Annotation\SearchApiBackend
 * @see \Drupal\search_api\Backend\BackendInterface
 * @see \Drupal\search_api\Backend\BackendPluginBase
 * @see plugin_api
 */
class BackendPluginManager extends SearchApiPluginManager {

  /**
   * Constructs a BackendPluginManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   The cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler, EventDispatcherInterface $eventDispatcher) {
    parent::__construct('Plugin/search_api/backend', $namespaces, $module_handler, $eventDispatcher, BackendInterface::class, SearchApiBackend::class);

    $this->alterInfo('search_api_backend_info');
    $this->alterEvent(SearchApiEvents::GATHERING_BACKENDS);
    $this->setCacheBackend($cache_backend, 'search_api_backends');
  }

}
