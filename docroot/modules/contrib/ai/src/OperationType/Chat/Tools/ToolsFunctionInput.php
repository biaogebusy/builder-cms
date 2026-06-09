<?php

namespace Drupal\ai\OperationType\Chat\Tools;

/**
 * The function for tools calling.
 */
class ToolsFunctionInput implements ToolsFunctionInputInterface {

  /**
   * The name of the function.
   *
   * @var string
   */
  private string $name = '';

  /**
   * The description of the function.
   *
   * @var string
   */
  private string $description = "";

  /**
   * The properties of the function.
   *
   * @var \Drupal\ai\OperationType\Chat\Tools\ToolsPropertyInputInterface[]
   */
  private array $properties = [];

  /**
   * The instructions.
   */
  public function __construct(string $name = "", array $function = []) {
    if (!empty($name) && !empty($function)) {
      $this->setFromArray($name, $function);
    }
    elseif (!empty($name)) {
      $this->setName($name);
    }
  }

  /**
   * {@inheritDoc}
   */
  public function getName(): string {
    return $this->name;
  }

  /**
   * {@inheritDoc}
   */
  public function setName(string $name) {
    $this->name = $name;
  }

  /**
   * {@inheritDoc}
   */
  public function getDescription(): string {
    return $this->description;
  }

  /**
   * {@inheritDoc}
   */
  public function setDescription(string $description) {
    $this->description = $description;
  }

  /**
   * {@inheritDoc}
   */
  public function getProperties(): array {
    return $this->properties;
  }

  /**
   * {@inheritDoc}
   */
  public function getPropertyByName(string $name): ?ToolsPropertyInputInterface {
    return $this->properties[$name] ?? NULL;
  }

  /**
   * {@inheritDoc}
   */
  public function setProperty(ToolsPropertyInputInterface $property) {
    $this->properties[$property->getName()] = $property;
  }

  /**
   * {@inheritDoc}
   */
  public function setProperties(array $properties) {
    foreach ($properties as $property) {
      $this->properties[$property->getName()] = $property;
    }
  }

  /**
   * {@inheritDoc}
   */
  public function unsetProperty(string $property_name) {
    unset($this->properties[$property_name]);
  }

  /**
   * {@inheritDoc}
   */
  public function getRequiredProperties(): array {
    return array_filter(
      $this->properties,
      fn (ToolsPropertyInputInterface $property) => $property->isRequired(),
    );
  }

  /**
   * {@inheritDoc}
   */
  public function setFromArray(string $name, array $function) {
    $this->name = $name;
    if (isset($function['description'])) {
      $this->description = $function['description'];
    }
    if (isset($function['properties'])) {
      $this->setProperties($function['properties']);
    }
  }

  /**
   * {@inheritDoc}
   */
  public function renderFunctionArray(): array {
    $properties = [];
    foreach ($this->properties as $property) {
      $properties[$property->getName()] = $property->renderPropertyArray();
    }
    $function = [
      'name' => $this->name,
      'description' => $this->description,
      'parameters' => NULL,
    ];
    if (!empty($properties)) {
      $function['parameters'] = [
        'type' => 'object',
        'properties' => $properties,
      ];
      foreach ($this->getRequiredProperties() as $property) {
        $function['parameters']['required'][] = $property->getName();
      }
    }
    return $function;
  }

  /**
   * From array.
   *
   * @param array $data
   *   The data to create the function from.
   *
   * @return \Drupal\ai\OperationType\Chat\Tools\ToolsFunctionInputInterface
   *   The function input.
   */
  public static function fromArray(array $data): ToolsFunctionInputInterface {
    $function = new static($data['name'] ?? '', $data);
    $function->setFromArray($data['name'] ?? '', $data);
    return $function;
  }

}
