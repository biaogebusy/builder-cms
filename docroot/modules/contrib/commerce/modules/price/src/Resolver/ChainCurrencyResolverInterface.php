<?php

namespace Drupal\commerce_price\Resolver;

/**
 * Runs the added resolvers one by one until one of them returns the currency.
 *
 * Each resolver in the chain can be another chain, which is why this interface
 * extends the base currency resolver one.
 */
interface ChainCurrencyResolverInterface extends CurrencyResolverInterface {}
