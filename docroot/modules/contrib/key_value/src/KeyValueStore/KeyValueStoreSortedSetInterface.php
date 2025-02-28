<?php

namespace Drupal\key_value\KeyValueStore;

interface KeyValueStoreSortedSetInterface extends KeyValueStoreSortedInterface {

  /**
   * @param float $score
   * @param mixed $member
   */
  public function add($score, $member);

  /**
   * @param array $pairs
   */
  public function addMultiple(array $pairs);

  /**
   * @param float $start
   * @param float $stop
   */
  public function deleteRange($start, $stop, $inclusive = TRUE);

  /**
   * @return float
   */
  public function getMaxScore();

  /**
   * @return float
   */
  public function getMinScore();

}
