<?php

namespace Drupal\replication\Plugin\ReplicationFilter;

use Drupal\Core\Entity\EntityInterface;

/**
 * Provides filtering by UUID.
 *
 * Use the configuration "uuids" which is an array of uuids, e.g. "101,102".
 *
 * Note: if the entity a UUID refers to references another entity, that
 * referenced entity's UUID must also be included in order to maintain data
 * integrity.
 *
 * @ReplicationFilter(
 *   id = "uuid",
 *   label = @Translation("Filter UUIDs"),
 *   description = @Translation("Replicate only entities in the set of UUIDs.")
 * )
 */
class UuidFilter extends ReplicationFilterBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'uuids' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function filter(EntityInterface $entity) {
    $configuration = $this->getConfiguration();
    return in_array($entity->uuid(), $configuration['uuids']);
  }

}
