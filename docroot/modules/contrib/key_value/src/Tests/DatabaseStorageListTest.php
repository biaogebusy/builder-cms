<?php

namespace Drupal\key_value\Tests;

use \PHPUnit\Framework\TestCase;

/**
 * Tests the list key-value database storage.
 *
 * @group key_value
 */
class DatabaseStorageListTest extends DatabaseStorageSortedTestBase {

  /**
   * @var \Drupal\key_value\KeyValueStore\KeyValueStoreListInterface
   */
  protected $store;

  public function setUp(): void {
    parent::setUp();
    $this->store = \Drupal::service('keyvalue.list')->get($this->collection);
  }

  public function testCalls() {
    $value0 = $this->randomMachineName();
    $key = $this->store->push($value0);
    $this->assertPairs([0 => $value0]);

    $value1 = $this->randomMachineName();
    $key = $this->store->push($value1);
    $this->assertPairs([1 => $value1]);

    $value2 = $this->randomMachineName();
    $value3 = $this->randomMachineName();
    $value4 = $this->randomMachineName();
    $keys = $this->store->pushMultiple([$value2, $value3, $value4]);
    $this->assertPairs([2 => $value2, 3 => $value3, 4 => $value4]);

    $count = $this->store->getCount();
    $this->assertEquals(5, $count, 'The count method returned correct count.');

    $value = $this->store->getRange(2, 4);
    $this->assertSame($value, [$value2, $value3, $value4]);

    $new3 = $this->randomMachineName();
    $this->store->set(3, $new3);
    $this->assertPairs([3 => $new3]);

    $value = $this->store->getRange(3, 3);
    $this->assertSame($value, [$new3], 'Value was successfully updated.');
    $this->assertRecords(5, 'Correct number of record in the collection after member update.');

    $value = $this->store->getRange(6, 10);
    $this->assertSame($value, [], 'Non-existing range returned empty array.');
  }
}
