<?php

namespace Drupal\ai\OperationType\Chat\Tools;

/**
 * The property results.
 */
interface ToolsPropertyResultInterface {

  /**
   * The constructor.
   *
   * @param \Drupal\ai\OperationType\Chat\Tools\ToolsPropertyInputInterface $inputProperty
   *   The input property.
   * @param mixed $value
   *   The value of the property.
   */
  public function __construct(ToolsPropertyInput $inputProperty, $value = NULL);

  /**
   * Get the name of the property.
   *
   * @return string
   *   The name of the property.
   */
  public function getName(): string;

  /**
   * Set the name of the property.
   *
   * @param string $name
   *   The name of the property.
   */
  public function setName(string $name);

  /**
   * Get the value of the property.
   *
   * @return mixed
   *   The value of the property.
   */
  public function getValue();

  /**
   * Set the value of the property.
   *
   * @param mixed|null $value
   *   The value of the property.
   */
  public function setValue($value);

  /**
   * Get the input property for validation.
   *
   * @return \Drupal\ai\OperationType\Chat\Tools\ToolsPropertyInputInterface
   *   The input property.
   */
  public function getInputProperty(): ToolsPropertyInputInterface;

  /**
   * Set the input property for validation.
   *
   * @param \Drupal\ai\OperationType\Chat\Tools\ToolsPropertyInputInterface $input
   *   The input property.
   */
  public function setInputProperty(ToolsPropertyInputInterface $input);

  /**
   * Validate the property.
   *
   * @throws \Drupal\ai\Exception\AiToolsValidationException
   *   If the property is invalid.
   */
  public function validate();

}
