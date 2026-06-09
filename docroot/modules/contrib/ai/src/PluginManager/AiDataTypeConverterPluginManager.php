<?php

declare(strict_types=1);

namespace Drupal\ai\PluginManager;

use Drupal\Component\Utility\SortArray;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\ai\Attribute\AiDataTypeConverter;
use Drupal\ai\Plugin\AiDataTypeConverter\AiDataTypeConverterInterface;

/**
 * AiDataTypeConverter plugin manager.
 */
final class AiDataTypeConverterPluginManager extends DefaultPluginManager {

  /**
   * The converter plugins.
   *
   * @var \Drupal\ai\Plugin\AiDataTypeConverter\AiDataTypeConverterInterface[]
   */
  protected $plugins;

  /**
   * Constructs the object.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/AiDataTypeConverter', $namespaces, $module_handler, AiDataTypeConverterInterface::class, AiDataTypeConverter::class);
    $this->alterInfo('ai_data_type_converter_info');
    $this->setCacheBackend($cache_backend, 'ai_data_type_converter_plugins');
  }

  /**
   * Upcasts a value to match data type expected in Context Definition.
   *
   * @param string $data_type
   *   The data type.
   * @param mixed $value
   *   The value.
   *
   * @return mixed
   *   The converted value.
   */
  public function convert(string $data_type, mixed $value): mixed {
    if (!isset($this->plugins)) {
      $plugin_definitions = $this->getDefinitions();
      // Sort by 'weight' property.
      uasort($plugin_definitions, [SortArray::class, 'sortByWeightElement']);
      foreach ($plugin_definitions as $plugin_id => $plugin_definition) {
        $this->plugins[$plugin_id] = $this->createInstance($plugin_id);
      }
    }
    foreach ($this->plugins as $plugin) {
      $result = $plugin->applies($data_type, $value);
      if ($result->applies() && $result->valid()) {
        $value = $plugin->convert($data_type, $value);
      }
    }
    return $value;
  }

}
