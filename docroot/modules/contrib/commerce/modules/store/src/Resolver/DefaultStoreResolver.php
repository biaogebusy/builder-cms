<?php

namespace Drupal\commerce_store\Resolver;

use Drupal\Core\Entity\EntityTypeManagerInterface;

/**
 * Returns the default store, if known.
 */
class DefaultStoreResolver implements StoreResolverInterface {

  /**
   * Constructs a new DefaultStoreResolver object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(protected EntityTypeManagerInterface $entityTypeManager) {}

  /**
   * {@inheritdoc}
   */
  public function resolve() {
    /** @var \Drupal\commerce_store\StoreStorageInterface $store_storage */
    $store_storage = $this->entityTypeManager->getStorage('commerce_store');
    return $store_storage->loadDefault();
  }

}
