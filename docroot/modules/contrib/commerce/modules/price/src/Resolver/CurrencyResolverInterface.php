<?php

namespace Drupal\commerce_price\Resolver;

use Drupal\commerce_price\Entity\CurrencyInterface;

/**
 * Defines the interface for currency resolvers.
 */
interface CurrencyResolverInterface {

  /**
   * Resolves a currency.
   *
   * @return \Drupal\commerce_price\Entity\CurrencyInterface|null
   *   A currency value object, if resolved. Otherwise NULL, indicating that the
   *   next resolver in the chain should be called.
   */
  public function resolve(): ?CurrencyInterface;

}
