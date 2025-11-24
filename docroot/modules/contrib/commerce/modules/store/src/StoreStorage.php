<?php

namespace Drupal\commerce_store;

use Drupal\commerce\CommerceContentEntityStorage;

/**
 * Defines the store storage.
 */
class StoreStorage extends CommerceContentEntityStorage implements StoreStorageInterface {

  /**
   * {@inheritdoc}
   */
  public function loadDefault() {
    $query = $this->getQuery();
    $query->accessCheck(FALSE);
    $query
      ->sort('is_default', 'DESC')
      ->sort('store_id', 'DESC')
      ->range(0, 1);
    $result = $query->execute();

    return $result ? $this->load(reset($result)) : NULL;
  }

}
