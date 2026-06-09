<?php

namespace Drupal\ai_ckeditor\PluginManager;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Provides an AI CKEditor plugin manager.
 *
 * @see \Drupal\ai_ckeditor\Attribute\AiCKEditor
 * @see \Drupal\ai_ckeditor\PluginInterfaces\AiCKEditorPluginInterface
 * @see plugin_api
 */
class AiCKEditorPluginManager extends DefaultPluginManager {

  /**
   * Constructs a AiCKEditor plugin object.
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
      'Plugin/AiCKEditor',
      $namespaces,
      $module_handler,
      'Drupal\ai_ckeditor\PluginInterfaces\AiCKEditorPluginInterface',
      'Drupal\ai_ckeditor\Attribute\AiCKEditor'
    );
    $this->alterInfo('ai_ckeditor');
    $this->setCacheBackend($cache_backend, 'ai_ckeditor_plugins');
  }

  /**
   * Finds plugin definitions.
   *
   * @return array
   *   List of definitions to store in cache.
   */
  protected function findDefinitions():array {
    $definitions = parent::findDefinitions();

    foreach ($definitions as $id => $definition) {
      if (!empty($definition['module_dependencies'])) {

        // Check if all modules are installed, otherwise remove this.
        foreach ($definition['module_dependencies'] as $module) {
          if (!$this->providerExists($module)) {
            unset($definitions[$id]);
            break;
          }
        }
      }
    }

    return $definitions;
  }

}
