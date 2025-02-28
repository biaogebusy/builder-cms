<?php

namespace Drupal\votingapi;

use Drupal\Core\Entity\EntityStorageInterface;

/**
 * Defines an interface for vote entity storage classes.
 */
interface VoteStorageInterface extends EntityStorageInterface {

  /**
   * Gets votes for a user.
   *
   * @param int $uid
   *   User ID.
   * @param string $vote_type_id
   *   The vote type ID.
   * @param string $entity_type_id
   *   The entity type ID.
   * @param int $entity_id
   *   The entity ID.
   * @param string $vote_source
   *   The vote source, only used if $uid == 0.
   *
   * @return mixed
   *   Returns the user votes.
   */
  public function getUserVotes($uid, $vote_type_id = NULL, $entity_type_id = NULL, $entity_id = NULL, $vote_source = NULL);

  /**
   * Deletes votes for a user.
   *
   * @param int $uid
   *   The User ID.
   * @param string $vote_type_id
   *   The vote type ID.
   * @param string $entity_type_id
   *   The entity type ID.
   * @param int $entity_id
   *   The entity ID.
   * @param string $vote_source
   *   The vote source, only used if $uid == 0.
   *
   * @return bool
   *   TRUE if the votes were deleted.
   */
  public function deleteUserVotes($uid, $vote_type_id = NULL, $entity_type_id = NULL, $entity_id = NULL, $vote_source = NULL);

  /**
   * Returns the default vote source.
   *
   * @param string $vote_source
   *   The vote source.
   *
   * @return string
   *   The $vote_source parameter or, if it is NULL, the default vote source.
   */
  public static function defaultVoteSource($vote_source = NULL);

  /**
   * Gets votes since a determined moment.
   *
   * @return mixed
   *   Returns the votes since last cron run.
   */
  public function getVotesSinceMoment();

  /**
   * Delets votes for deleted entity everywhere in the database.
   *
   * @param string $entity_type_id
   *   The entity type ID.
   * @param int $entity_id
   *   The entity ID.
   *
   * @return bool
   *   TRUE if the votes were deleted.
   */
  public function deleteVotesForDeletedEntity($entity_type_id, $entity_id);

}
