<?php

namespace Drupal\ai\Service\FunctionCalling;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\ContextAwarePluginInterface;
use Drupal\ai\OperationType\Chat\Tools\ToolsFunctionInput;
use Drupal\ai\OperationType\Chat\Tools\ToolsFunctionOutput;

/**
 * Defines an interface for AI function calling services.
 */
interface FunctionCallInterface extends PluginInspectionInterface, ContextAwarePluginInterface {

  /**
   * Gets the assigned tools id, if any.
   *
   * @return string
   *   The tools id.
   */
  public function getToolsId(): string;

  /**
   * Sets the assigned tools id.
   *
   * @param string $tools_id
   *   The tools id.
   */
  public function setToolsId(string $tools_id);

  /**
   * Gets the function name.
   *
   * @return string
   *   The function name.
   */
  public function getFunctionName(): string;

  /**
   * Populates the values.
   *
   * @param \Drupal\ai\OperationType\Chat\Tools\ToolsFunctionOutput $output
   *   The tools function output.
   */
  public function populateValues(ToolsFunctionOutput $output);

  /**
   * Normalize the function call input.
   *
   * @return \Drupal\ai\OperationType\Chat\Tools\ToolsFunctionInput
   *   The normalized input.
   */
  public function normalize(): ToolsFunctionInput;

}
