<?php

namespace Drupal\ctools;

use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\TempStore\SharedTempStore;

/**
 * An extension of the SharedTempStore system for serialized data.
 *
 * @deprecated in ctools:8.x-3.10 and is removed from ctools:4.0.0.
 *   Use \Drupal\Core\TempStore\SharedTempStore instead.
 * @see https://www.drupal.org/project/ctools/issues/3300044
 */
class SerializableTempstore extends SharedTempStore {
  use DependencySerializationTrait;

}
