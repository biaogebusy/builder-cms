<?php

namespace Drupal\ai_automators\PluginManager;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\ai_automators\Attribute\AiAutomatorProcessRule;

/**
 * Provides an OpenAI Automator Field process plugin manager.
 *
 * @see \Drupal\ai_automators\Attribute\AiAutomatorProcessRule
 * @see \Drupal\ai_automators\PluginInterfaces\AiAutomatorFieldProcessInterface
 * @see plugin_api
 */
class AiAutomatorFieldProcessManager extends DefaultPluginManager {

  /**
   * Constructs a AiAutomatorFieldProcess object.
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
      'Plugin/AiAutomatorProcess',
      $namespaces,
      $module_handler,
      'Drupal\ai_automators\PluginInterfaces\AiAutomatorFieldProcessInterface',
      AiAutomatorProcessRule::class
    );
    $this->alterInfo('ai_automator_process');
    $this->setCacheBackend($cache_backend, 'ai_automator_process_plugins');
  }

}
