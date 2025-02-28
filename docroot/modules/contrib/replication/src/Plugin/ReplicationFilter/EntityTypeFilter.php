<?php

namespace Drupal\replication\Plugin\ReplicationFilter;

use Drupal\Core\Entity\EntityInterface;
use Drupal\replication\Plugin\ReplicationFilter\ReplicationFilterBase;

/**
 * Provides a filter based on entity type.
 *
 * Use the configuration "types" which is an array of values in the format
 * "{entity_type_id}.{bundle}".
 *
 * @ReplicationFilter(
 *   id = "entity_type",
 *   label = @Translation("Filter By Entity Type"),
 *   description = @Translation("Replicate only entities that match a given type.")
 * )
 */
class EntityTypeFilter extends ReplicationFilterBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'types' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function filter(EntityInterface $entity) {
    $configuration = $this->getConfiguration();
    $types = $configuration['types'];

    foreach ($types as $type) {
      // Handle cases like "node.".
      $type = trim($type, '.');

      $split = explode('.', $type);

      $entity_type_id = $split[0];
      $bundle = isset($split[1]) ? $split[1] : NULL;

      // Filter for only the entity type id.
      if ($bundle == NULL && $entity->getEntityTypeId() == $entity_type_id) {
        return TRUE;
      }

      // Filter for both the entity type id and bundle.
      if ($entity->getEntityTypeId() == $entity_type_id
        && $entity->bundle() == $bundle) {
        return TRUE;
      }
    }

    return FALSE;
  }

}
