<?php

namespace Drupal\key_value\KeyValueStore;

use Drupal\Component\Serialization\SerializationInterface;
use Drupal\Core\Database\Connection;

abstract class DatabaseStorageSortedBase implements KeyValueStoreSortedInterface {

  /**
   * @var string
   */
  protected $collection;

  /**
   * @var \Drupal\Component\Serialization\SerializationInterface
   */
  protected $serializer;

  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * @var string
   */
  protected $table;

  public function __construct($collection, SerializationInterface $serializer, Connection $connection,  $table = 'key_value_sorted') {
    $this->collection = $collection;
    $this->serializer = $serializer;
    $this->connection = $connection;
    $this->table = $table;
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

  /**
   * {@inheritdoc}
   */
  public function getRange($start, $stop = NULL, $inclusive = TRUE) {
    $query = $this->connection->select($this->table, 't')
      ->fields('t', ['value'])
      ->condition('collection', $this->collection)
      ->condition('name', $start, $inclusive ? '>=' : '>');

    if ($stop !== NULL) {
      $query->condition('name', $stop, $inclusive ? '<=' : '<');
    }
    $result = $query->orderBy('name', 'ASC')->execute();

    $values = [];
    foreach ($result as $item) {
      $values[] = $this->serializer->decode($item->value);
    }
    return $values;
  }
}
