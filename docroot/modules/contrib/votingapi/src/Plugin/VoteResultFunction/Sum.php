<?php

namespace Drupal\votingapi\Plugin\VoteResultFunction;

use Drupal\votingapi\VoteResultFunctionBase;

/**
 * A sum of a set of votes.
 *
 * @VoteResultFunction(
 *   id = "vote_sum",
 *   label = @Translation("Sum"),
 *   description = @Translation("The total of all vote values.")
 * )
 */
class Sum extends VoteResultFunctionBase {

  /**
   * {@inheritdoc}
   */
  public function calculateResult($votes) {
    $total = 0;
    foreach ($votes as $vote) {
      $total += $vote->getValue();
    }
    return $total;
  }

}
