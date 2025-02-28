<?php

namespace Drupal\votingapi;

use Drupal\Core\Entity\EntityStorageInterface;

/**
 * Defines an interface for vote entity storage classes.
 */
interface VoteResultStorageInterface extends EntityStorageInterface {

  /**
   * Gets vote results for a VoteResult entity.
   *
   * This function gives back the number of votes for a particular entity with a
   * particular type of voting. For example it can be used to get number of
   * likes and also dislikes. Just need to change the type.
   *
   * @param string $entity_type_id
   *   The entity type id.
   * @param int $entity_id
   *   The node id of the node for which number of votes is requested.
   * @param string $vote_type
   *   Plugin implementing \Drupal\votingapi\Plugin\VoteType.
   * @param string $function
   *   The function.
   *
   * @return \Drupal\votingapi\Entity\VoteResult[]
   *   The number of votes for the entity.
   */
  public function getEntityResults($entity_type_id, $entity_id, $vote_type, $function);

}
