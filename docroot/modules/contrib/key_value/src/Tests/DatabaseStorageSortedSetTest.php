<?php

namespace Drupal\key_value\Tests;

use \PHPUnit\Framework\TestCase;

/**
 * Tests the sorted set key-value database storage.
 *
 * @group key_value
 */
class DatabaseStorageSortedSetTest extends DatabaseStorageSortedTestBase {

  /**
   * @var \Drupal\key_value\KeyValueStore\KeyValueStoreSortedSetInterface
   */
  protected $store;

  public function setUp(): void {
    parent::setUp();
    $this->store = \Drupal::service('keyvalue.sorted_set')->get($this->collection);
  }

  public function testCalls() {
    $key0 = $this->newKey();
    $value0 = $this->randomMachineName();
    $this->store->add($key0, $value0);
    $this->assertPairs([$key0 => $value0]);

    $key1 = $this->newKey();
    $value1 = $this->randomMachineName();
    $this->store->add($key1, $value1);
    $this->assertPairs([$key1 => $value1]);

    // Ensure it works to add sets with the same score.
    $key2 = $this->newKey();
    $key3 = $this->newKey();
    $value2 = $this->randomMachineName();
    $value3 = $this->randomMachineName();
    $value4 = $this->randomMachineName();
    $value5 = $this->randomMachineName();
    $this->store->addMultiple([
      [$key2 => $value2],
      [$key2 => $value3],
      [$key2 => $value4],
      [$key3 => $value5],
    ]);

    $count = $this->store->getCount();
    $this->assertEquals(6, $count, 'The count method returned correct count.');

    $value = $this->store->getRange($key1, $key2);
    $this->assertSame($value, [$value1, $value2, $value3, $value4]);

    $value = $this->store->getRange($key1, NULL);
    $this->assertSame($value, [$value1, $value2, $value3, $value4, $value5]);

    $value = $this->store->getRange($key1, NULL, FALSE);
    $this->assertSame($value, [$value2, $value3, $value4, $value5]);

    $value = $this->store->getRange($key1, $key3, FALSE);
    $this->assertSame($value, [$value2, $value3, $value4]);

    $new1 = $this->newKey();
    $this->store->add($new1, $value1);

    $value = $this->store->getRange($new1, $new1);
    $this->assertSame($value, [$value1], 'Member was successfully updated.');
    $this->assertRecords(6, 'Correct number of record in the collection after member update.');

    $value = $this->store->getRange($key1, $key1);
    $this->assertSame($value, [], 'Non-existing range returned empty array.');

    $max_score = $this->store->getMaxScore();
    $this->assertEquals($new1, $max_score, 'The getMaxScore method returned correct score.');

    $min_score = $this->store->getMinScore();
    $this->assertEquals($key0, $min_score, 'The getMinScore method returned correct score.');
  }
}
