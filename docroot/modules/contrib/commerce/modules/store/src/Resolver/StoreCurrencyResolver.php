<?php

namespace Drupal\commerce_store\Resolver;

use Drupal\commerce_price\Entity\CurrencyInterface;
use Drupal\commerce_price\Resolver\CurrencyResolverInterface;
use Drupal\commerce_store\CurrentStoreInterface;

/**
 * Returns the store's default currency.
 */
class StoreCurrencyResolver implements CurrencyResolverInterface {

  /**
   * Constructs a new StoreCurrencyResolver object.
   */
  public function __construct(protected CurrentStoreInterface $currentStore) {}

  /**
   * {@inheritdoc}
   */
  public function resolve(): ?CurrencyInterface {
    return $this->currentStore?->getStore()?->getDefaultCurrency();
  }

}
