<?php

namespace Drupal\ai\PluginManager;

use Drupal\ai\Attribute\ChatProcessor;
use Drupal\ai\Plugin\ChatProcessor\ChatProcessorInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Manages ChatProcessor plugins.
 *
 * @see \Drupal\ai\Attribute\ChatProcessor
 * @see \Drupal\ai\Plugin\ChatProcessor\ChatProcessorInterface
 * @see plugin_api
 */
class ChatProcessorPluginManager extends DefaultPluginManager {

  /**
   * Constructs a ChatProcessorPluginManager object.
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
    parent::__construct('Plugin/ChatProcessor', $namespaces, $module_handler, ChatProcessorInterface::class, ChatProcessor::class);
    $this->setCacheBackend($cache_backend, 'chat_processor_plugins');
    $this->alterInfo('chat_processor_info');
  }

}
