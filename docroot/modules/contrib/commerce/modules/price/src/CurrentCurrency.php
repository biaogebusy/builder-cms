<?php

namespace Drupal\commerce_price;

use Drupal\commerce_price\Entity\CurrencyInterface;
use Drupal\commerce_price\Resolver\ChainCurrencyResolverInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Holds a reference to the current currency, resolved on demand.
 *
 * The ChainCurrencyResolver runs the registered currency resolvers one by one
 * until one of them returns the currency.
 * The StoreCurrencyResolver runs last, and will select the default
 * store's currency.
 * Custom resolvers can choose based on the url, the user's country, etc.
 *
 * @see \Drupal\commerce_price\Resolver\ChainCurrencyResolver
 * @see \Drupal\commerce_store\Resolver\StoreCurrencyResolver
 */
class CurrentCurrency implements CurrentCurrencyInterface {

  /**
   * Static cache of resolved currencies. One per request.
   */
  protected \SplObjectStorage $currencies;

  /**
   * Constructs a new CurrentCurrency object.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   * @param \Drupal\commerce_price\Resolver\ChainCurrencyResolverInterface $chainResolver
   *   The chain currency resolver.
   */
  public function __construct(protected RequestStack $requestStack, protected ChainCurrencyResolverInterface $chainResolver) {
    $this->currencies = new \SplObjectStorage();
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrency(): ?CurrencyInterface {
    $request = $this->requestStack->getCurrentRequest();
    if (!$request || !$this->currencies->contains($request)) {
      $currency = $this->chainResolver->resolve();
      if (!$request) {
        return $currency;
      }
      $this->currencies[$request] = $currency;
    }

    return $this->currencies[$request];
  }

}
