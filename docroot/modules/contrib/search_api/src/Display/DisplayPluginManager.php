<?php

namespace Drupal\search_api\Display;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\search_api\Event\SearchApiEvents;
use Drupal\search_api\SearchApiPluginManager;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Manages display plugins.
 *
 * @see \Drupal\search_api\Annotation\SearchApiDisplay
 * @see \Drupal\search_api\Display\DisplayInterface
 * @see \Drupal\search_api\Display\DisplayPluginBase
 * @see plugin_api
 */
class DisplayPluginManager extends SearchApiPluginManager implements DisplayPluginManagerInterface {

  /**
   * Static cache for the display plugins.
   *
   * @var \Drupal\search_api\Display\DisplayInterface[]|null
   *
   * @see \Drupal\search_api\Display\DisplayPluginManager::getInstances()
   */
  protected $displays = NULL;

  /**
   * Constructs a new class instance.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Symfony\Contracts\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler, EventDispatcherInterface $eventDispatcher) {
    parent::__construct('Plugin/search_api/display', $namespaces, $module_handler, $eventDispatcher, 'Drupal\search_api\Display\DisplayInterface', 'Drupal\search_api\Annotation\SearchApiDisplay');

    $this->setCacheBackend($cache_backend, 'search_api_displays');
    $this->alterInfo('search_api_displays');
    $this->alterEvent(SearchApiEvents::GATHERING_DISPLAYS);
  }

  /**
   * {@inheritdoc}
   */
  public function getInstances() {
    if ($this->displays === NULL) {
      $this->displays = [];

      foreach ($this->getDefinitions() as $name => $display_definition) {
        if (class_exists($display_definition['class']) && empty($this->displays[$name])) {
          $display = $this->createInstance($name);
          $this->displays[$name] = $display;
        }
      }
    }

    return $this->displays;
  }

  /**
   * {@inheritdoc}
   */
  public function clearCachedDefinitions() {
    parent::clearCachedDefinitions();

    $this->discovery = NULL;
  }

}
