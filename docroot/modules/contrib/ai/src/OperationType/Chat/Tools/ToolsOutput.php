<?php

namespace Drupal\ai\OperationType\Chat\Tools;

/**
 * The tools.
 */
class ToolsOutput implements ToolsOutputInterface {

  /**
   * The input tools.
   *
   * @var \Drupal\ai\OperationType\Chat\Tools\ToolsInputInterface
   */
  private ToolsInputInterface $input;

  /**
   * The functions that should be used.
   *
   * @var \Drupal\ai\OperationType\Chat\Tools\ToolsFunctionOutputInterface[]
   *   The functions.
   */
  private array $functions = [];

  /**
   * {@inheritDoc}
   */
  public function __construct(ToolsInputInterface $input, array $tools = []) {
    $this->input = $input;
    foreach ($tools as $tool) {
      // Currently only functions.
      if ($tool instanceof ToolsFunctionOutputInterface) {
        $this->setFunction($tool);
      }
    }
  }

  /**
   * {@inheritDoc}
   */
  public function getInput(): ToolsInputInterface {
    return $this->input;
  }

  /**
   * {@inheritDoc}
   */
  public function setFunctions(array $functions) {
    foreach ($functions as $function) {
      $this->setFunction($function);
    }
  }

  /**
   * {@inheritDoc}
   */
  public function setFunction(ToolsFunctionOutputInterface $function) {
    $input_function = $this->getInputFunctionByName($function->getName());
    $function->setInputFunction($input_function);
    $this->functions[] = $function;
  }

  /**
   * {@inheritDoc}
   */
  public function getFunctions(): array {
    return $this->functions;
  }

  /**
   * {@inheritDoc}
   */
  public function getInputFunctionByName(string $name): ?ToolsFunctionInputInterface {
    return $this->input->getFunctionByName($name);
  }

  /**
   * {@inheritDoc}
   */
  public function getOutputRenderArray(): array {
    $output = [];
    foreach ($this->functions as $function) {
      $output[] = $function->getOutputRenderArray();
    }
    return $output;
  }

  /**
   * {@inheritDoc}
   */
  public function validate() {
    // Validate each function and argument.
    foreach ($this->functions as $function) {
      $function->validate();
    }
    // Validate that each function is a valid function.
    foreach ($this->functions as $function) {
      $input_function = $this->getInputFunctionByName($function->getName());
      if (!$input_function) {
        throw new \Exception('Invalid function returned: ' . $function->getName());
      }
    }
  }

}
