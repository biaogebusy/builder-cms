<?php

namespace Drupal\ai\Utility;

use Symfony\Component\Yaml\Yaml;

/**
 * Predefined models data.
 */
class PredefinedModels {

  /**
   * Get predefined models.
   *
   * @param string|null $operation_type
   *   The operation type.
   *
   * @return array
   *   The list of models.
   */
  public static function getPredefinedModels(string|NULL $operation_type = NULL) {
    // Make sure that var is just alphanumeric and no directory traversal.
    if ($operation_type && (!ctype_alnum($operation_type) || strpos($operation_type, '..') !== FALSE)) {
      return [];
    }
    $file = \Drupal::moduleHandler()->getModule('ai')->getPath() . '/resources/common_models/' . $operation_type . '.yml';
    if (!file_exists($file)) {
      return [];
    }
    return Yaml::parseFile($file);
  }

  /**
   * Get predefined model.
   *
   * @param string $operation_type
   *   The operation type.
   * @param string $model_id
   *   The model ID.
   *
   * @return array
   *   The model.
   */
  public static function getPredefinedModel(string $operation_type, string $model_id) {
    $models = self::getPredefinedModels($operation_type);
    return $models[$model_id] ?? [];
  }

  /**
   * Get predefined models as options.
   *
   * @param string|null $operation_type
   *   The operation type.
   *
   * @return array
   *   The list of models.
   */
  public static function getPredefinedModelsAsOptions(string|NULL $operation_type = NULL) {
    $models = self::getPredefinedModels($operation_type);
    $options = [];
    foreach ($models as $model_id => $model) {
      $options[$model_id] = $model['label'];
    }
    // Order by label.
    asort($options);
    return $options;
  }

}
