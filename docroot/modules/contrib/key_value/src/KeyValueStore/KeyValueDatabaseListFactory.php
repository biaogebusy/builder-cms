<?php

namespace Drupal\key_value\KeyValueStore;

use Drupal\Component\Serialization\SerializationInterface;
use Drupal\Core\Database\Connection;

class KeyValueDatabaseListFactory implements KeyValueListFactoryInterface {

  /**
   * @var \Drupal\Component\Serialization\SerializationInterface
   */
  protected $serializer;

  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  /**
   * @param \Drupal\Component\Serialization\SerializationInterface $serializer
   * @param \Drupal\Core\Database\Connection $connection
   */
  function __construct(SerializationInterface $serializer, Connection $connection) {
    $this->serializer = $serializer;
    $this->connection = $connection;
  }

  /**
   * {@inheritdoc}
   */
  public function get($collection) {
    return new DatabaseStorageList($collection, $this->serializer, $this->connection);
  }
}
