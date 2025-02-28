<?php

namespace Drupal\commerce_price\Repository;

use CommerceGuys\Intl\Currency\CurrencyRepositoryInterface as ExternalRepositoryInterface;

interface CurrencyRepositoryInterface extends ExternalRepositoryInterface {

  /**
   * Gets the default number of fraction digits for the given currency code.
   *
   * Merchants are allowed to override the fraction digits through the UI,
   * which can have an unexpected effect on payment gateways, which use that
   * information when converting amounts to minor units.
   *
   * @param string $currency_code
   *   The currency code.
   *
   * @return int
   *   The number of fraction digits.
   *
   * @throws \CommerceGuys\Intl\Exception\UnknownCurrencyException
   *   Thrown if the given currency code is unknown.
   */
  public function getDefaultFractionDigits(string $currency_code): int;

}
