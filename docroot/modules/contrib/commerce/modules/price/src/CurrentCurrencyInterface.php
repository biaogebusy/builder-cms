<?php

namespace Drupal\commerce_price;

use Drupal\commerce_price\Entity\CurrencyInterface;

/**
 * Holds a reference to the active currency, resolved on demand.
 */
interface CurrentCurrencyInterface {

  /**
   * Gets the active currency for the current request.
   *
   * @return \Drupal\commerce_price\Entity\CurrencyInterface|null
   *   The active currency.
   */
  public function getCurrency(): ?CurrencyInterface;

}
