<?php

declare(strict_types = 1);

namespace Drupal\entity_share_server;

/**
 * Defines the Operators helper class.
 */
class OperatorsHelper {

  /**
   * Helper function to get the operator options.
   *
   * @return array
   *   An array of options.
   */
  public static function getOperatorOptions() {
    return [
      '=' => '=',
      '<>' => '<>',
      '<' => '<',
      '<=' => '<=',
      '>' => '>',
      '>=' => '>=',
      'STARTS_WITH' => 'STARTS_WITH',
      'CONTAINS' => 'CONTAINS',
      'ENDS_WITH' => 'ENDS_WITH',
      'IN' => 'IN',
      'NOT IN' => 'NOT IN',
      'BETWEEN' => 'BETWEEN',
      'NOT BETWEEN' => 'NOT BETWEEN',
      'IS NULL' => 'IS NULL',
      'IS NOT NULL' => 'IS NOT NULL',
    ];
  }

  /**
   * Helper function to get the stand alone operators.
   *
   * Operators that do not require a value to be entered.
   *
   * @return array
   *   An array of options.
   */
  public static function getStandAloneOperators() {
    return [
      'IS NULL' => 'IS NULL',
      'IS NOT NULL' => 'IS NOT NULL',
    ];
  }

  /**
   * Helper function to get the multiple values operators.
   *
   * Operators that allow to have multiple values entered.
   *
   * @return array
   *   An array of options.
   */
  public static function getMultipleValuesOperators() {
    return [
      'IN' => 'IN',
      'NOT IN' => 'NOT IN',
      'BETWEEN' => 'BETWEEN',
      'NOT BETWEEN' => 'NOT BETWEEN',
    ];
  }

}
