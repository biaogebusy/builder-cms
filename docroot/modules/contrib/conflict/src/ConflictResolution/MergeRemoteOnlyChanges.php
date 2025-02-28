<?php

namespace Drupal\conflict\ConflictResolution;

use Drupal\conflict\ConflictTypes;
use Drupal\conflict\Event\EntityConflictResolutionEvent;

class MergeRemoteOnlyChanges extends MergeStrategyBase {

  /**
   * {@inheritdoc}
   */
  public function getMergeStrategyId() : string {
    return 'conflict.merge_remote_only_changes';
  }

  /**
   * {@inheritdoc}
   */
  public function resolveConflictsContentEntity(EntityConflictResolutionEvent $event) {
    /** @var \Drupal\Core\Entity\ContentEntityInterface $remote_entity */
    $remote_entity = $event->getRemoteEntity();
    /** @var \Drupal\Core\Entity\ContentEntityInterface $result_entity */
    $result_entity = $event->getResultEntity();

    foreach ($event->getConflicts() as $property => $conflict_type) {
      if ($conflict_type === ConflictTypes::CONFLICT_TYPE_REMOTE) {
        $result_entity->set($property, $remote_entity->get($property)->getValue());
        $event->removeConflict($property);
      }
    }
  }

}
