<?php

namespace Drupal\votingapi;

/**
 * Provides an interface for a VoteResultFunction plugin.
 *
 * @see \Drupal\votingapi\Annotation\VoteResultFunction
 * @see \Drupal\votingapi\VoteManager
 * @see \Drupal\votingapi\VoteResultFunctionBase
 * @see plugin_api
 */
interface VoteResultFunctionInterface {

  /**
   * Retrieve the label for the voting result.
   *
   * @return string
   *   The translated label
   */
  public function getLabel();

  /**
   * Retrieve the description for the voting result.
   *
   * @return string
   *   The translated description
   */
  public function getDescription();

  /**
   * Performs the calculations on a set of votes to derive the result.
   *
   * @param \Drupal\votingapi\Entity\Vote[] $votes
   *   An array of Vote entities.
   *
   * @return int
   *   A result based on the supplied votes.
   */
  public function calculateResult($votes);

}
