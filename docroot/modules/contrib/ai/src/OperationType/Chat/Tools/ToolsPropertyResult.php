<?php

namespace Drupal\ai\OperationType\Chat\Tools;

use Drupal\ai\Exception\AiToolsValidationException;
use Drupal\ai\Traits\OperationType\ChatTrait;

/**
 * The property results.
 */
class ToolsPropertyResult implements ToolsPropertyResultInterface {

  use ChatTrait;

  /**
   * The name of the property.
   *
   * @var string
   */
  private string $name;

  /**
   * The value of the property.
   *
   * @var mixed|null
   */
  private $value = NULL;

  /**
   * The input property.
   *
   * @var \Drupal\ai\OperationType\Chat\Tools\ToolsPropertyInputInterface|null
   */
  private ?ToolsPropertyInputInterface $inputProperty = NULL;

  /**
   * {@inheritDoc}
   */
  public function __construct(?ToolsPropertyInput $inputProperty = NULL, $value = NULL) {
    if ($inputProperty) {
      $this->setInputProperty($inputProperty);
      $this->setName($inputProperty->getName());
    }
    $this->setValue($value);
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
  public function getValue() {
    return $this->value;
  }

  /**
   * {@inheritDoc}
   */
  public function setValue($value) {
    switch (gettype($value)) {
      case 'string':
        $value = $this->getHostnameFilterService()->filterText((string) $value);
        break;

      case 'array':
        // Itterate all values that are strings and filter them.
        foreach ($value as $key => $item) {
          if (is_string($item)) {
            $value[$key] = $this->getHostnameFilterService()->filterText((string) $item);
          }
        }
        break;
    }
    $this->value = $value;
  }

  /**
   * {@inheritDoc}
   */
  public function getInputProperty(): ToolsPropertyInputInterface {
    return $this->inputProperty;
  }

  /**
   * {@inheritDoc}
   */
  public function setInputProperty(ToolsPropertyInputInterface $input) {
    $this->inputProperty = $input;
  }

  /**
   * {@inheritDoc}
   */
  public function validate() {
    // If the input property is not set, we cannot validate.
    if ($this->inputProperty === NULL) {
      return;
    }
    // Do all basic validation we can do.
    $type = $this->inputProperty->getType();
    $name = $this->inputProperty->getName();

    // Validate type.
    if (in_array($type, ['string', 'str'])) {
      if (!is_string($this->value)) {
        throw new AiToolsValidationException("Property value of $name is not a string.");
      }
      if (is_int($this->inputProperty->getMaxLength()) && strlen($this->value) > $this->inputProperty->getMaxLength()) {
        throw new AiToolsValidationException("Property value of $name is too long. Max length is " . $this->inputProperty->getMaxLength() . ".");
      }
      if (is_int($this->inputProperty->getMinLength()) && strlen($this->value) < $this->inputProperty->getMinLength()) {
        throw new AiToolsValidationException("Property value of $name is too short. Min length is " . $this->inputProperty->getMinLength() . ".");
      }
      // Email.
      if ($this->inputProperty->getFormat() == 'email' && !filter_var($this->value, FILTER_VALIDATE_EMAIL)) {
        throw new AiToolsValidationException("Property value of $name is not a valid email.");
      }
      // Url.
      if ($this->inputProperty->getFormat() == 'url' && !filter_var($this->value, FILTER_VALIDATE_URL)) {
        throw new AiToolsValidationException("Property value of $name is not a valid URL.");
      }
      // Date.
      if ($this->inputProperty->getFormat() == 'date' && !strtotime($this->value)) {
        throw new AiToolsValidationException("Property value of $name is not a valid date.");
      }
      // Date-time.
      if ($this->inputProperty->getFormat() == 'date-time' && !strtotime($this->value)) {
        throw new AiToolsValidationException("Property value of $name is not a valid date-time.");
      }
    }
    if (in_array($type, ['number', 'int', 'float', 'integer', 'double'])) {
      if (!is_numeric($this->value)) {
        throw new AiToolsValidationException("Property value of $name is not a number.");
      }
      if (is_numeric($this->inputProperty->getMaximum()) && $this->value > $this->inputProperty->getMaximum()) {
        throw new AiToolsValidationException("Property value of $name is too high. Max value is " . $this->inputProperty->getMaximum() . ".");
      }
      if (is_numeric($this->inputProperty->getMinimum()) && $this->value < $this->inputProperty->getMinimum()) {
        throw new AiToolsValidationException("Property value of $name is too low. Min value is " . $this->inputProperty->getMinimum() . ".");
      }
    }
    if (in_array($type, ['boolean', 'bool'])) {
      if (!is_bool($this->value)) {
        throw new AiToolsValidationException("Property value of $name is not a boolean.");
      }
    }
    if (in_array($type, ['array'])) {
      if (!is_null($this->value) && !is_array($this->value)) {
        throw new AiToolsValidationException("Property value of $name is not an array.");
      }
    }
    if (in_array($type, ['object'])) {
      if (!is_null($this->value) && !is_object($this->value)) {
        throw new AiToolsValidationException("Property value of $name is not an object.");
      }
    }
    if ($type === 'null') {
      if (!is_null($this->value)) {
        throw new AiToolsValidationException("Property value of $name is not null.");
      }
    }
  }

  /**
   * Fix the value.
   *
   * @param mixed $value
   *   The value to fix.
   *
   * @return mixed
   *   The fixed value.
   */
  protected function fixValue($value) {
    // We fix booleans.
    if (in_array($this->inputProperty->getType(), ['boolean', 'bool'])) {
      // We fix string values.
      if (is_string($value) || is_numeric($value)) {
        $value = strtolower($value);
        if (in_array($value, ['true', '1', 'yes', 'on'])) {
          $value = TRUE;
        }
        if (in_array($value, ['false', '0', 'no', 'off'])) {
          $value = FALSE;
        }
      }
    }
    return $value;
  }

}
