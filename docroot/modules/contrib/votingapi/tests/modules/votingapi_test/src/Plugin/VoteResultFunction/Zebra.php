<?php

namespace Drupal\votingapi_test\Plugin\VoteResultFunction;

use Drupal\votingapi\VoteResultFunctionBase;

/**
 * A test plugin for the Voting API module.
 *
 * @VoteResultFunction(
 *   id = "zebra",
 *   label = @Translation("Zebra"),
 *   description = @Translation("A vote test plugin.")
 * )
 */
class Zebra extends VoteResultFunctionBase {

  /**
   * {@inheritdoc}
   */
  public function calculateResult($votes) {
    return 10101;
  }

}
