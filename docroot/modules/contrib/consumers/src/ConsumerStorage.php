<?php

namespace Drupal\consumers;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;

/**
 * Defines the storage handler class for consumers.
 *
 * This extends the base storage class, adding required special handling for
 * consumers.
 */
class ConsumerStorage extends SqlContentEntityStorage {

  /**
   * {@inheritdoc}
   */
  public function restore(EntityInterface $entity) {
    /** @var \Drupal\consumers\Entity\ConsumerInterface $entity */
    // Special handling for the secret field added by simple_oauth,
    // make sure that it is not hashed again.
    if ($entity->hasField('secret')) {
      $entity->get('secret')->pre_hashed = TRUE;
    }
    parent::restore($entity);
  }

}
