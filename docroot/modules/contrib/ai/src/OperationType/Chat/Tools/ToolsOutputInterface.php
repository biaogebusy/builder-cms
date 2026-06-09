<?php

namespace Drupal\ai\OperationType\Chat\Tools;

/**
 * The tools interface for outputs.
 */
interface ToolsOutputInterface {

  /**
   * The constructor.
   *
   * @param \Drupal\ai\OperationType\Chat\Tools\ToolsInputInterface $input
   *   The input.
   * @param \Drupal\ai\OperationType\Chat\Tools\ToolsInterface[] $tools
   *   The tools that should be used.
   */
  public function __construct(ToolsInputInterface $input, array $tools);

  /**
   * Get input interface.
   *
   * @return \Drupal\ai\OperationType\Chat\Tools\ToolsInputInterface
   *   The input interface.
   */
  public function getInput(): ToolsInputInterface;

  /**
   * Sets multiple functions.
   *
   * @param \Drupal\ai\OperationType\Chat\Tools\ToolsFunctionOutputInterface[] $functions
   *   The functions to add.
   */
  public function setFunctions(array $functions);

  /**
   * Sets a function. Updates if the function already exists with the same name.
   *
   * @param \Drupal\ai\OperationType\Chat\Tools\ToolsFunctionOutputInterface $function
   *   The function to add.
   */
  public function setFunction(ToolsFunctionOutputInterface $function);

  /**
   * Get the functions.
   *
   * @return \Drupal\ai\OperationType\Chat\ToolsFunctionOutputInterface[]
   *   The functions.
   */
  public function getFunctions(): array;

  /**
   * Get the input function with name.
   *
   * @param string $name
   *   The name of the function.
   */
  public function getInputFunctionByName(string $name): ?ToolsFunctionInputInterface;

  /**
   * Gets an output render array for providers.
   *
   * @return array
   *   The render array.
   */
  public function getOutputRenderArray(): array;

  /**
   * Validate the tools, functions and its arguments.
   *
   * @throws \Drupal\ai\Exception\AiToolsValidationException
   */
  public function validate();

}
