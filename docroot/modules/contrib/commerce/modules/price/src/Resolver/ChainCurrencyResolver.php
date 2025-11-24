<?php

namespace Drupal\commerce_price\Resolver;

use Drupal\commerce_price\Entity\CurrencyInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Default implementation of the chain base currency resolver.
 */
class ChainCurrencyResolver implements ChainCurrencyResolverInterface {

  /**
   * Constructs a new ChainCurrencyResolver object.
   *
   * @param iterable<\Drupal\commerce_price\Resolver\CurrencyResolverInterface> $resolvers
   *   The resolvers.
   */
  public function __construct(
    #[AutowireIterator(tag: 'commerce_price.currency_resolver')]
    protected iterable $resolvers,
  ) {
    foreach ($resolvers as $resolver) {
      if (!($resolver instanceof CurrencyResolverInterface)) {
        throw new \InvalidArgumentException(sprintf(
          'All currency resolvers must implement %s, but got %s.',
          CurrencyResolverInterface::class,
          get_debug_type($resolver)
        ));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function resolve(): ?CurrencyInterface {
    foreach ($this->resolvers as $resolver) {
      $result = $resolver->resolve();
      if ($result) {
        return $result;
      }
    }

    return NULL;
  }

}
