<?php

namespace Drupal\ai\OperationType\Chat\Tools;

/**
 * The tools interface.
 */
interface ToolsInputInterface {

  /**
   * The constructor.
   *
   * @param \Drupal\ai\OperationType\Chat\Tools\ToolsInterface[] $functions
   *   The functions.
   */
  public function __construct(array $functions = []);

  /**
   * Sets multiple functions.
   *
   * @param \Drupal\ai\OperationType\Chat\Tools\ToolsFunctionInputInterface[] $functions
   *   The functions to add.
   */
  public function setFunctions(array $functions);

  /**
   * Sets a function. Updates if the function already exists with the same name.
   *
   * @param \Drupal\ai\OperationType\Chat\ToolsFunctionInputInterface $function
   *   The function to add.
   */
  public function setFunction(ToolsFunctionInputInterface $function);

  /**
   * Get the functions.
   *
   * @return \Drupal\ai\OperationType\Chat\Tools\ToolsFunctionInputInterface[]
   *   The functions.
   */
  public function getFunctions(): array;

  /**
   * Get the function by name.
   *
   * @param string $name
   *   The name of the function.
   *
   * @return \Drupal\ai\OperationType\Chat\Tools\ToolsFunctionInputInterface|null
   *   The function or NULL.
   */
  public function getFunctionByName(string $name): ?ToolsFunctionInputInterface;

  /**
   * Remove a function.
   *
   * @param string $name
   *   The name of the function.
   */
  public function removeFunction(string $name);

  /**
   * Render the tools array.
   *
   * This can be used as an example and might not fit the actual implementation.
   *
   * @return array
   *   The tools array.
   */
  public function renderToolsArray(): array;

}
