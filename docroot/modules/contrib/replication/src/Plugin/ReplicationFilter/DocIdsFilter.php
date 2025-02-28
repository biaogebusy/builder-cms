<?php

namespace Drupal\replication\Plugin\ReplicationFilter;

use Drupal\Core\Entity\EntityInterface;

/**
 * Provides filtering by UUID.
 *
 * This filter does the same as UuidFilter, but uses different filter and
 * parameter names. We use it for keeping the compatibility with CouchDB.
 *
 * Use the configuration "doc_ids" which is an array of uuids, e.g. "101,102".
 *
 * Note: if the entity a UUID refers to references another entity, that
 * referenced entity's UUID must also be included in order to maintain data
 * integrity.
 *
 * @ReplicationFilter(
 *   id = "_doc_ids",
 *   label = @Translation("Filter doc IDs (UUIDs)"),
 *   description = @Translation("Replicate only entities in the set of doc IDs (UUIDs).")
 * )
 */
class DocIdsFilter extends ReplicationFilterBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'doc_ids' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function filter(EntityInterface $entity) {
    $configuration = $this->getConfiguration();
    return in_array($entity->uuid(), $configuration['doc_ids']);
  }

}
