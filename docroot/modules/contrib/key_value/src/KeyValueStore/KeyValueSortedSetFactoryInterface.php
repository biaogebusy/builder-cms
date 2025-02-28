<?php

namespace Drupal\key_value\KeyValueStore;

interface KeyValueSortedSetFactoryInterface {

  /**
   * @param string $collection
   *
   * @return \Drupal\key_value\KeyValueStore\KeyValueStoreSortedSetInterface
   */
  public function get($collection);

}
