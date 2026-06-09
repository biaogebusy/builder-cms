<?php

namespace Drupal\ai\Traits\Utility;

/**
 * A trait for getting function calls.
 */
trait FunctionCallTrait {

  /**
   * Get the function call plugin manager.
   *
   * @return \Drupal\ai\Service\FunctionCalling\FunctionCallPluginManager
   *   The function call plugin manager.
   */
  protected function getFunctionCallPluginManager() {
    return \Drupal::service('plugin.manager.ai.function_calls');
  }

  /**
   * Get the function call group manager.
   *
   * @return \Drupal\ai\Service\FunctionCalling\FunctionCallGroupManager
   *   The function call group manager.
   */
  protected function getFunctionCallGroupManager() {
    return \Drupal::service('plugin.manager.ai.function_call_groups');
  }

}
