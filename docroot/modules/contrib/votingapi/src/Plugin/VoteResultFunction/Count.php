<?php

namespace Drupal\votingapi\Plugin\VoteResultFunction;

use Drupal\votingapi\VoteResultFunctionBase;

/**
 * A sum of a set of votes.
 *
 * @VoteResultFunction(
 *   id = "vote_count",
 *   label = @Translation("Count"),
 *   description = @Translation("The number of votes cast.")
 * )
 */
class Count extends VoteResultFunctionBase {

  /**
   * {@inheritdoc}
   */
  public function calculateResult($votes) {
    return count($votes);
  }

}
