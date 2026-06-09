<?php

namespace Drupal\ai\Traits\Helper;

use Drupal\ai\Service\FunctionCalling\FunctionCallPluginManager;

/**
 * The tools calling trait.
 */
trait ToolsCallingTrait {

  /**
   * Verify that a function is only alphanumeric with underscores.
   *
   * @param string $function_name
   *   The function name.
   */
  public function verifyFunctionName(string $function_name) {
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $function_name)) {
      throw new \InvalidArgumentException('The function name must be alphanumeric with underscores.');
    }
  }

  /**
   * Get the function calling plugin manager.
   *
   * @return \Drupal\ai\Service\FunctionCalling\FunctionCallPluginManager
   *   The function calling plugin manager.
   */
  public function getFunctionCallPluginManager(): FunctionCallPluginManager {
    return \Drupal::service('ai.function_calls');
  }

}
