<?php

namespace Drupal\key_value\KeyValueStore;

use \PHPUnit\Framework\TestCase;

class DatabaseStorageList extends DatabaseStorageSortedBase implements KeyValueStoreListInterface {

  /**
   * {@inheritdoc}
   */
  public function delete($count, $value) {
    // @todo
  }

  /**
   * {@inheritdoc}
   */
  public function pop() {
    // @todo
  }

  /**
   * {@inheritdoc}
   */
  public function push($value): void {
    $this->pushMultiple([$value]);
  }

  /**
   * {@inheritdoc}
   */
  public function pushMultiple(array $values): void {
    // @todo Find out if there's a way to do this query/sub-query combination
    // in one atomic operation.
    foreach ($values as $value) {
      $sub_query = $this->connection->select($this->table, 't')
        ->condition('t.collection', $this->collection);
      $sub_query->addExpression(':collection', 'collection', [':collection' => $this->collection]);
      $sub_query->addExpression('IFNULL(MAX(t.name) + 1, 0)', 'name');
      $sub_query->addExpression(':value', 'value', [':value' => $this->serializer->encode($value)]);

      $this->connection->insert($this->table)
        ->from($sub_query)
        ->execute();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function set($key, $value) {
    $this->connection->update($this->table)
      ->fields([
        'value' => $this->serializer->encode($value)
      ])
      ->condition('collection', $this->collection)
      ->condition('name', (int) $key)
      ->execute();
  }

  /**
   * {@inheritdoc}
   */
  public function shift() {
    // @todo
  }
  
  /**
   * {@inheritdoc}
   */
  public function unshift($value): void {
    // @todo
  }
  
  /**
   * {@inheritdoc}
   */
  public function unshiftMultiple(array $values): void {
    // @todo
  }
}
