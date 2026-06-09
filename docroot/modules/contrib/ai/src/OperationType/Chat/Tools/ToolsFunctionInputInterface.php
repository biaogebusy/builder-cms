<?php

namespace Drupal\ai\OperationType\Chat\Tools;

/**
 * The function interface.
 */
interface ToolsFunctionInputInterface extends ToolsInterface {

  /**
   * The constructor.
   *
   * @param string $name
   *   The data name of the property.
   * @param array $function
   *   The function array.
   */
  public function __construct(string $name = "", array $function = []);

  /**
   * Get the data name of the function.
   *
   * @return string
   *   The name of the function.
   */
  public function getName(): string;

  /**
   * Set the data name of the function.
   *
   * @param string $name
   *   The name of the function.
   */
  public function setName(string $name);

  /**
   * Get the description of the function.
   *
   * @return string
   *   The description of the function.
   */
  public function getDescription(): string;

  /**
   * Set the description of the function.
   *
   * @param string $description
   *   The description of the function.
   */
  public function setDescription(string $description);

  /**
   * Get the properties of the function.
   *
   * @return \Drupal\ai\OperationType\Chat\Tools\ToolsPropertyInputInterface[]
   *   The properties of the function.
   */
  public function getProperties(): array;

  /**
   * Get a property by name.
   *
   * @param string $name
   *   The name of the property.
   *
   * @return null|\Drupal\ai\OperationType\Chat\Tools\ToolsPropertyInputInterface
   *   The property.
   */
  public function getPropertyByName(string $name): ?ToolsPropertyInputInterface;

  /**
   * Set one property of the function.
   *
   * @param \Drupal\ai\OperationType\Chat\Tools\ToolsPropertyInputInterface $property
   *   The property of the function.
   */
  public function setProperty(ToolsPropertyInputInterface $property);

  /**
   * Set the properties of the function.
   *
   * @param \Drupal\ai\OperationType\Chat\Tools\ToolsPropertyInputInterface[] $properties
   *   The properties of the function.
   */
  public function setProperties(array $properties);

  /**
   * Unset a property.
   *
   * @param string $property_name
   *   The property name.
   */
  public function unsetProperty(string $property_name);

  /**
   * Gets the required properties.
   *
   * @return \Drupal\ai\OperationType\Chat\Tools\ToolsPropertyInputInterface[]
   *   The required properties.
   */
  public function getRequiredProperties(): array;

  /**
   * Set values from an array.
   *
   * @param string $name
   *   The name of the function.
   * @param array $function
   *   The function array.
   */
  public function setFromArray(string $name, array $function);

  /**
   * Renders the functions array.
   *
   * @return array
   *   The functions array.
   */
  public function renderFunctionArray(): array;

}
