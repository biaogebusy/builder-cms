<?php

namespace Drupal\key_value\KeyValueStore;

/**
 * Defines the expirable key/value store factory interface.
 */
interface KeyValueListFactoryInterface {

  /**
   * @param string $collection
   *
   * @return \Drupal\key_value\KeyValueStore\KeyValueStoreListInterface
   */
  public function get($collection);

}
