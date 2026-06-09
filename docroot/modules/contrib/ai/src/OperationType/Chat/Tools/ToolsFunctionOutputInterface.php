<?php

namespace Drupal\ai\OperationType\Chat\Tools;

/**
 * The function interface.
 */
interface ToolsFunctionOutputInterface extends ToolsInterface {

  /**
   * The constructor.
   *
   * @param \Drupal\ai\OperationType\Chat\Tools\ToolsFunctionInputInterface $input
   *   The input.
   * @param string $tool_id
   *   The id of the tool.
   * @param \Drupal\ai\OperationType\Chat\Tools\ToolsPropertyResultInterface[] $arguments
   *   The arguments.
   */
  public function __construct(ToolsFunctionInputInterface $input, string $tool_id, array $arguments = []);

  /**
   * Set the tool id.
   *
   * @param string $tool_id
   *   The tool id.
   */
  public function setToolId(string $tool_id);

  /**
   * Get the tool id.
   *
   * @return string
   *   The tool id.
   */
  public function getToolId(): string;

  /**
   * Get the input function.
   *
   * @return \Drupal\ai\OperationType\Chat\Tools\ToolsFunctionInputInterface|null
   *   The input.
   */
  public function getInputFunction(): ToolsFunctionInputInterface|NULL;

  /**
   * Set the input function.
   *
   * @param \Drupal\ai\OperationType\Chat\Tools\ToolsFunctionInputInterface|null $input
   *   The input.
   */
  public function setInputFunction(?ToolsFunctionInputInterface $input);

  /**
   * Get the arguments.
   *
   * @return \Drupal\ai\OperationType\Chat\Tools\ToolsPropertyResultInterface[]
   *   The arguments.
   */
  public function getArguments(): array;

  /**
   * Add all the arguments.
   *
   * @param \Drupal\ai\OperationType\Chat\Tools\ToolsPropertyResultInterface[] $arguments
   *   The arguments.
   */
  public function addArguments(array $arguments);

  /**
   * Add one argument.
   *
   * @param \Drupal\ai\OperationType\Chat\Tools\ToolsPropertyResultInterface $argument
   *   The argument.
   */
  public function addArgument(ToolsPropertyResultInterface $argument);

  /**
   * Get the name of the function.
   *
   * @return string
   *   The name of the function.
   */
  public function getName(): string;

  /**
   * Set the name of the function.
   *
   * @param string $name
   *   The name of the function.
   */
  public function setName(string $name);

  /**
   * Gets the output render array for providers.
   *
   * @return array
   *   The render array.
   */
  public function getOutputRenderArray(): array;

  /**
   * Validate the function and its arguments.
   *
   * @throws \Drupal\ai\Exception\AiToolsValidationException
   */
  public function validate();

}
