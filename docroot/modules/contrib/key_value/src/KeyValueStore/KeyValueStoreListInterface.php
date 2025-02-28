<?php

namespace Drupal\key_value\KeyValueStore;

interface KeyValueStoreListInterface extends KeyValueStoreSortedInterface {

  /**
   * @param integer $count
   * @param mixed $value
   */
  public function delete($count, $value);

  /**
   * @return mixed
   */
  public function pop();

  /**
   * @param mixed $value
   *
   * @return null
   */
  public function push($value);

  /**
   * @param array $values
   *
   * @return null
   */
  public function pushMultiple(array $values);

  /**
   * @param float $key
   * @param mixed $value
   */
  public function set($key, $value);

  /**
   * @return mixed
   */
  public function shift();

  /**
   * @param mixed $value
   *
   * @return integer
   */
  public function unshift($value);

  /**
   * @param array $values
   *
   * @return array
   */
  public function unshiftMultiple(array $values);

}
