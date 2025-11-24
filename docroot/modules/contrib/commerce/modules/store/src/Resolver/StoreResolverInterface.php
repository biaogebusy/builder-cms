<?php

namespace Drupal\commerce_store\Resolver;

/**
 * Defines the interface for store resolvers.
 *
 * Interface for services tagged as 'commerce_store.store_resolver'.
 */
interface StoreResolverInterface {

  /**
   * Resolves the store.
   *
   * @return \Drupal\commerce_store\Entity\StoreInterface|null
   *   The store, if resolved. Otherwise NULL, indicating that the next
   *   resolver in the chain should be called.
   */
  public function resolve();

}
