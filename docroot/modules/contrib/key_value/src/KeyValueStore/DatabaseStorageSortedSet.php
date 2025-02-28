<?php

namespace Drupal\key_value\KeyValueStore;

class DatabaseStorageSortedSet extends DatabaseStorageSortedBase implements KeyValueStoreSortedSetInterface {

  /**
   * {@inheritdoc}
   */
  public function add($score, $member) {
    $this->addMultiple([[$score => $member]]);
  }

  /**
   * {@inheritdoc}
   */
  public function addMultiple(array $pairs) {
    // @todo Find out if we can to multiple merge queries in one atomic
    // operation.
    foreach ($pairs as $pair) {
      foreach ($pair as $score => $member) {
        $encoded_member = $this->serializer->encode($member);
        $this->connection->merge($this->table)
          ->fields([
            'collection' => $this->collection,
            'name' => $score,
            'value' => $encoded_member,
          ])
          ->condition('collection', $this->collection)
          ->condition('value', $encoded_member)
          ->execute();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function deleteRange($start, $stop, $inclusive = TRUE) {
    // @todo
  }

  /**
   * {@inheritdoc}
   */
  public function getCount() {
    return $this->connection->select($this->table, 't')
      ->condition('collection', $this->collection)
      ->countQuery()
      ->execute()
      ->fetchField();
  }

  public function getMaxScore() {
    $query = $this->connection->select($this->table);
    $query->condition('collection', $this->collection, '=');
    $query->addExpression('MAX(name)');
    return $query->execute()->fetchField();
  }

  public function getMinScore() {
    $query = $this->connection->select($this->table);
    $query->condition('collection', $this->collection, '=');
    $query->addExpression('MIN(name)');
    return $query->execute()->fetchField();
  }
}
