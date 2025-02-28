<?php

namespace Drupal\key_value\Tests;

use \Drupal\KernelTests\KernelTestBase;
use \PHPUnit\Framework\TestCase;

abstract class DatabaseStorageSortedTestBase extends KernelTestBase {

  static public $modules = ['serialization', 'key_value'];

  /**
   * @var string
   */
  protected $collection;

  /**
   * @var \Drupal\Component\Serialization\SerializationInterface
   */
  protected $serializer;

  /**
   * @var \Drupal\multiversion\MultiversionManagerInterface
   */
  protected $multiversionManager;

  /**
   * @var \Drupal\Core\Database\Connection
   */
  protected $connection;

  public function setUp(): void {
    parent::setUp();
    $this->installSchema('key_value', ['key_value_sorted']);

    $this->collection = $this->randomMachineName();
    $this->serializer = \Drupal::service('serialization.phpserialize');
    $this->connection = \Drupal::service('database');
  }

  public function assertPairs($expected_pairs) {
    $result = $this->connection->select('key_value_sorted', 't')
      ->fields('t', ['name', 'value'])
      ->condition('collection', $this->collection)
      ->condition('name', array_keys($expected_pairs), 'IN')
      ->execute()
      ->fetchAllAssoc('name');

    $expected_count = count($expected_pairs);
    $this->assertSame(count($result), $expected_count, "Query affected $expected_count records.");
    foreach ($expected_pairs as $key => $value) {
      $this->assertSame($this->serializer->decode($result[$key]->value), $value, "Key $key have value $value");
    }
  }

  public function assertRecords($expected, $message = NULL) {
    $count = $this->connection->select('key_value_sorted', 't')
      ->fields('t')
      ->condition('collection', $this->collection)
      ->countQuery()
      ->execute()
      ->fetchField();
    $this->assertEquals($expected, $count, $message ? $message : "There are $expected records.");
  }

  public function newKey() {
    return (int) (microtime(TRUE) * 1000000);
  }
}
