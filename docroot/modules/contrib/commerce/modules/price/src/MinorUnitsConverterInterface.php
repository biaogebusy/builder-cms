<?php

namespace Drupal\commerce_price;

/**
 * Allows converting between Price objects (decimal) and minor units (integer).
 */
interface MinorUnitsConverterInterface {

  /**
   * Converts an amount in "minor unit" to a decimal amount.
   *
   * For example, 999 USD becomes 9.99.
   *
   * @param int|string $amount
   *   The amount in minor unit.
   * @param string $currency_code
   *   The currency code.
   *
   * @return \Drupal\commerce_price\Price
   *   The decimal price.
   */
  public function fromMinorUnits($amount, $currency_code): Price;

  /**
   * Converts the given amount to its minor units.
   *
   * For example, 9.99 USD becomes 999.
   *
   * @param \Drupal\commerce_price\Price $amount
   *   The amount.
   *
   * @return int
   *   The amount in minor units, as an integer.
   */
  public function toMinorUnits(Price $amount): int;

}
