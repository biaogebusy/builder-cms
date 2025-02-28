<?php

namespace Drupal\fivestar;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\votingapi\VoteResultFunctionManager;

/**
 * Contains methods for managing vote results.
 */
class VoteResultManager {

  /**
   * The vote result manager.
   *
   * @var \Drupal\votingapi\VoteResultFunctionManager
   */
  protected $voteResultManager;

  /**
   * Constructs a new VoteResultManager object.
   *
   * @param \Drupal\votingapi\VoteResultFunctionManager $vote_result_manager
   *   The vote result manager.
   */
  public function __construct(VoteResultFunctionManager $vote_result_manager) {
    $this->voteResultManager = $vote_result_manager;
  }

  /**
   * Get votes for passed entity based on vote type.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   * @param string $vote_type
   *
   * @return array
   */
  public function getResultsByVoteType(FieldableEntityInterface $entity, $vote_type) {
    $results = $this->getResults($entity);
    if (isset($results[$vote_type])) {
      return $results[$vote_type];
    }

    return $this->getDefaultResults();
  }

  /**
   * Get all votes results for passed entity.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *
   * @return array
   */
  public function getResults(FieldableEntityInterface $entity) {
    $results = $this->voteResultManager->getResults(
      $entity->getEntityTypeId(),
      $entity->id()
    );

    return !empty($results) ? $results : $this->getDefaultResults();
  }

  /**
   * Return default result collection.
   *
   * @return array
   *   An associative array with keys:
   *   - vote_sum: The sum of all votes.
   *   - vote_user: The user's vote.
   *   - vote_count: The number of votes.
   *   - vote_average: The average of all votes.
   */
  public function getDefaultResults() {
    return [
      'vote_sum' => 0,
      'vote_user' => 0,
      'vote_count' => 0,
      'vote_average' => 0,
    ];
  }

  /**
   * Recalculate votes results.
   *
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   */
  public function recalculateResults(FieldableEntityInterface $entity) {
    $this->voteResultManager->recalculateResults(
      $entity->getEntityTypeId(),
      $entity->id(),
      $entity->bundle()
    );
  }

}
