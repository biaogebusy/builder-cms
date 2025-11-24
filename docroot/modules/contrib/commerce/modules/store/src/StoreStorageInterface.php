<?php

namespace Drupal\commerce_store;

use Drupal\Core\Entity\ContentEntityStorageInterface;

/**
 * Defines the interface for store storage.
 */
interface StoreStorageInterface extends ContentEntityStorageInterface {

  /**
   * Loads the default store.
   *
   * @return \Drupal\commerce_store\Entity\StoreInterface|null
   *   The default store, if known.
   */
  public function loadDefault();

}
