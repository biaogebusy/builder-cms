<?php

namespace Drupal\entity\QueryAccess;

/**
 * Represents a single query access condition.
 */
final class Condition {

  /**
   * The supported operators.
   *
   * @var string[]
   */
  protected static $supportedOperators = [
    '=', '<>', '<', '<=', '>', '>=', 'BETWEEN', 'NOT BETWEEN',
    'IN', 'NOT IN', 'IS NULL', 'IS NOT NULL',
  ];

  /**
   * The field.
   *
   * @var string
   */
  protected $field;

  /**
   * The value.
   *
   * @var mixed
   */
  protected $value;

  /**
   * The operator.
   *
   * @var string
   */
  protected $operator;

  /**
   * Constructs a new Condition object.
   *
   * @param string $field
   *   The field, with an optional column name. E.g: 'uid', 'address.locality'.
   * @param mixed $value
   *   The value.
   * @param string $operator
   *   The operator.
   *   Possible values: =, <>, <, <=, >, >=, BETWEEN, NOT BETWEEN,
   *                   IN, NOT IN, IS NULL, IS NOT NULL.
   */
  public function __construct($field, $value, $operator = NULL) {
    // Provide a default based on the data type of the value.
    if (!isset($operator)) {
      $operator = is_array($value) ? 'IN' : '=';
    }
    // Validate the selected operator.
    if (!in_array($operator, self::$supportedOperators)) {
      throw new \InvalidArgumentException(sprintf('Unrecognized operator "%s".', $operator));
    }

    $this->field = $field;
    $this->value = $value;
    $this->operator = $operator;
  }

  /**
   * {@inheritdoc}
   */
  public function getField() {
    return $this->field;
  }

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    return $this->value;
  }

  /**
   * {@inheritdoc}
   */
  public function getOperator() {
    return $this->operator;
  }

  /**
   * Gets the string representation of the condition.
   *
   * Used for debugging purposes.
   *
   * @return string
   *   The string representation of the condition.
   */
  public function __toString() {
    if (in_array($this->operator, ['IS NULL', 'IS NOT NULL'])) {
      return "{$this->field} {$this->operator}";
    }
    else {
      if (is_array($this->value)) {
        $value = "['" . implode("', '", $this->value) . "']";
      }
      else {
        $value = "'" . $this->value . "'";
      }

      return "{$this->field} {$this->operator} $value";
    }
  }

}
