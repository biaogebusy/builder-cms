<?php

declare(strict_types = 1);

namespace Drupal\entity_share_diff\DiffGenerator;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;

/**
 * Provides a DiffManager plugin manager.
 *
 * @ingroup field_diff_generator
 *
 * @see \Drupal\entity_share_diff\Annotation\DiffGenerator
 * @see \Drupal\entity_share_diff\DiffGenerator\DiffGeneratorInterface
 * @see plugin_api
 */
class DiffGeneratorPluginManager extends DefaultPluginManager {

  /**
   * Plugin definitions.
   *
   * @var array
   */
  protected $pluginDefinitions;

  /**
   * Constructs a DiffManager object.
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
    'Plugin/DiffGenerator',
    $namespaces,
    $module_handler,
    'Drupal\entity_share_diff\DiffGenerator\DiffGeneratorInterface',
    'Drupal\entity_share_diff\Annotation\DiffGenerator'
    );
    $this->setCacheBackend($cache_backend, 'field_diff_generator_plugins');
  }

  /**
   * Creates a plugin instance for a field definition.
   *
   * Creates the instance based on the selected plugin for the field.
   *
   * @param string $field_type
   *   The field type.
   *
   * @return \Drupal\entity_share_diff\DiffGenerator\DiffGeneratorInterface|null
   *   The plugin instance, NULL if none.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function createInstanceForFieldDefinition(string $field_type) {
    if (!isset($this->pluginDefinitions)) {
      foreach ($this->getDefinitions() as $plugin_definition) {
        if (isset($plugin_definition['field_types'])) {
          // Iterate through all the field types this plugin supports
          // and for every such field type add the id of the plugin.
          if (!isset($plugin_definition['weight'])) {
            $plugin_definition['weight'] = 0;
          }

          foreach ($plugin_definition['field_types'] as $id) {
            $this->pluginDefinitions[$id][$plugin_definition['id']]['weight'] = $plugin_definition['weight'];
          }
          $plugins = $this->pluginDefinitions;
        }
      }
    }
    else {
      $plugins = $this->pluginDefinitions;
    }
    // Build a list of all diff plugins supporting the field type of the field.
    $plugin_options = [];
    if (isset($plugins[$field_type])) {
      // Sort the plugins based on their weight.
      uasort($plugins[$field_type], 'Drupal\Component\Utility\SortArray::sortByWeightElement');

      foreach (array_keys($plugins[$field_type]) as $id) {
        $definition = $this->getDefinition($id, FALSE);
        // Check if the plugin is applicable.
        if (isset($definition['class']) && in_array($field_type, $definition['field_types'])) {
          $plugin_options[$id] = $this->getDefinitions()[$id]['label'];
        }
      }
      $settings = key($plugin_options);
      return $this->createInstance($settings, []);
    }
    return NULL;
  }

}
