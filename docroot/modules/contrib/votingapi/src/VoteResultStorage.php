<?php

namespace Drupal\votingapi;

use Drupal\votingapi\Entity\VoteResult;
use Drupal\Core\Entity\Sql\SqlContentEntityStorage;

/**
 * Storage class for vote entities.
 *
 * This extends the \Drupal\entity\EntityDatabaseStorage class, adding
 * required special handling for vote entities.
 */
class VoteResultStorage extends SqlContentEntityStorage implements VoteResultStorageInterface {

  /**
   * {@inheritdoc}
   */
  public function getEntityResults($entity_type_id, $entity_id, $vote_type, $function) {
    $query = \Drupal::entityQuery('vote_result')
      ->condition('entity_type', $entity_type_id)
      ->condition('entity_id', $entity_id)
      ->condition('type', $vote_type);
    if (!empty($function)) {
      $query->condition('function', $function);
    }
    $query->sort('type');
    $vote_ids = $query->execute();
    return VoteResult::loadMultiple($vote_ids);
  }

}
