<?php

namespace Drupal\ctools;

use Drupal\Core\TempStore\SharedTempStoreFactory;
use Drupal\Core\TempStore\SharedTempStore as CoreSharedTempStore;

/**
 * A factory for creating SerializableTempStore objects.
 *
 * @deprecated in ctools:8.x-3.10 and is removed from ctools:4.0.0.
 *   Use \Drupal\Core\TempStore\SharedTempStoreFactory instead.
 *
 * @see https://www.drupal.org/project/ctools/issues/3300044
 */
class SerializableTempstoreFactory extends SharedTempStoreFactory {

  /**
   * {@inheritdoc}
   */
  public function get($collection, $owner = NULL) {
    // Use the currently authenticated user ID or the active user ID unless the
    // owner is overridden.
    if (!isset($owner)) {
      $owner = $this->currentUser->id() ?: session_id();
    }

    // Store the data for this collection in the database.
    $storage = $this->storageFactory->get("tempstore.shared.$collection");
    return new CoreSharedTempStore($storage, $this->lockBackend, $owner, $this->requestStack, $this->currentUser, $this->expire);
  }

}
