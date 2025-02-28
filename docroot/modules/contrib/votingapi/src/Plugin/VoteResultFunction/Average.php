<?php

namespace Drupal\votingapi\Plugin\VoteResultFunction;

use Drupal\votingapi\VoteResultFunctionBase;

/**
 * A sum of a set of votes.
 *
 * @VoteResultFunction(
 *   id = "vote_average",
 *   label = @Translation("Average"),
 *   description = @Translation("The average vote value.")
 * )
 */
class Average extends VoteResultFunctionBase {

  /**
   * {@inheritdoc}
   */
  public function calculateResult($votes) {
    $total = 0;
    foreach ($votes as $vote) {
      $total += $vote->getValue();
    }
    return ($total / count($votes));
  }

}
