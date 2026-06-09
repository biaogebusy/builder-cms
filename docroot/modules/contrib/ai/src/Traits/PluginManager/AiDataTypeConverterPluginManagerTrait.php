<?php

namespace Drupal\ai\Traits\PluginManager;

use Drupal\ai\PluginManager\AiDataTypeConverterPluginManager;

/**
 * Trait for AiDataTypeConverterPluginManager.
 *
 * This trait provides a method to get the AiDataTypeConverterPluginManager.
 */
trait AiDataTypeConverterPluginManagerTrait {

  /**
   * Gets the AiDataTypeConverterPluginManager.
   *
   * @return \Drupal\ai\PluginManager\AiDataTypeConverterPluginManager
   *   The AiDataTypeConverterPluginManager.
   */
  protected function getAiDataTypeConverterPluginManager(): AiDataTypeConverterPluginManager {
    return \Drupal::service('plugin.manager.ai_data_type_converter');
  }

}
