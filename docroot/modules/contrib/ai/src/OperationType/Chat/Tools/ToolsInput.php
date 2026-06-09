<?php

namespace Drupal\ai\OperationType\Chat\Tools;

/**
 * The tools.
 */
class ToolsInput implements ToolsInputInterface, \JsonSerializable {

  /**
   * The functions.
   *
   * @var \Drupal\ai\OperationType\Chat\Tools\ToolsFunctionInputInterface[]
   */
  private array $functions = [];

  /**
   * {@inheritDoc}
   */
  public function __construct(array $functions = []) {
    foreach ($functions as $function) {
      // Currently only functions.
      if ($function instanceof ToolsFunctionInputInterface) {
        $this->setFunction($function);
      }
    }
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
  public function setFunction(ToolsFunctionInputInterface $function) {
    $this->functions[$function->getName()] = $function;
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
  public function getFunctionByName(string $name): ?ToolsFunctionInputInterface {
    return $this->functions[$name] ?? NULL;
  }

  /**
   * {@inheritDoc}
   */
  public function removeFunction(string $name) {
    unset($this->functions[$name]);
  }

  /**
   * {@inheritDoc}
   */
  public function renderToolsArray(): array {
    $tools = [];
    foreach ($this->functions as $function) {
      $tools[] = [
        'type' => 'function',
        'function' => $function->renderFunctionArray(),
      ];
    }
    return $tools;
  }

  /**
   * {@inheritDoc}
   */
  public function toArray(): array {
    return $this->renderToolsArray();
  }

  /**
   * {@inheritDoc}
   */
  public function jsonSerialize(): array {
    return $this->renderToolsArray();
  }

  /**
   * Create the tools input from an array.
   *
   * @param array $array
   *   The array to create the tools input from.
   *
   * @return \Drupal\ai\OperationType\Chat\Tools\ToolsInputInterface
   *   The tools input.
   */
  public static function fromArray(array $array): ToolsInputInterface {
    $functions = [];
    if (isset($array['functions']) && is_array($array['functions'])) {
      foreach ($array['functions'] as $function) {
        $functions[] = ToolsFunctionInput::fromArray($function);
      }
    }
    return new static($functions);
  }

}
