<?php

namespace Drupal\commerce_store;

use Drupal\commerce_store\Resolver\ChainStoreResolverInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Holds a reference to the current store, resolved on demand.
 *
 * The ChainStoreResolver runs the registered store resolvers one by one until
 * one of them returns the store.
 * The DefaultStoreResolver runs last, and will select the default store.
 * Custom resolvers can choose based on the url, the user's country, etc.
 *
 * Note that this functionality is optional, since not every site will be
 * limited to having only one active store at the time.
 *
 * @see \Drupal\commerce_store\Resolver\ChainStoreResolver
 * @see \Drupal\commerce_store\Resolver\DefaultStoreResolver
 */
class CurrentStore implements CurrentStoreInterface {

  /**
   * Static cache of resolved stores. One per request.
   *
   * @var \SplObjectStorage
   */
  protected $stores;

  /**
   * Constructs a new CurrentStore object.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   * @param \Drupal\commerce_store\Resolver\ChainStoreResolverInterface $chainResolver
   *   The chain resolver.
   */
  public function __construct(protected RequestStack $requestStack, protected ChainStoreResolverInterface $chainResolver) {
    $this->stores = new \SplObjectStorage();
  }

  /**
   * {@inheritdoc}
   */
  public function getStore() {
    $request = $this->requestStack->getCurrentRequest();
    if (!$request || !$this->stores->contains($request)) {
      $store = $this->chainResolver->resolve();
      if (!$request) {
        return $store;
      }
      $this->stores[$request] = $store;
    }

    return $this->stores[$request];
  }

}
