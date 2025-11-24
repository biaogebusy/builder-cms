<?php

namespace Drupal\redis\Queue;

use Drupal\Core\Queue\ReliableQueueInterface;

/**
 * Redis queue implementation using PhpRedis extension backend.
 *
 * @ingroup queue
 */
class ReliableRedisQueue extends RedisQueue implements ReliableQueueInterface {

  /**
   * {@inheritdoc}
   */
  public function createItem($data) {
    $record = new \stdClass();
    $record->data = $data;
    $record->item_id = $this->incrementId();
    // We cannot rely on REQUEST_TIME because many items might be created
    // by a single request which takes longer than 1 second.
    $record->timestamp = time();

     $this->client->multi();
     $this->client->hsetnx($this->availableItems, $record->item_id, serialize($record));
     $this->client->lLen($this->availableListKey);
     $this->client->lpush($this->availableListKey, $record->item_id);
     $result = $this->client->exec();

    $success = $result[0] && $result[2] > $result[1];

    return $success ? $record->item_id : FALSE;
  }

  /**
   * Gets next serial ID for Redis queue items.
   *
   * @return int
   *   Next serial ID for Redis queue item.
   */
  protected function incrementId() {
    return $this->client->incr($this->incrementCounterKey);
  }

  /**
   * {@inheritdoc}
   */
  public function numberOfItems() {
    return $this->client->lLen($this->availableListKey) + $this->client->lLen($this->claimedListKey);
  }

  /**
   * {@inheritdoc}
   */
  public function claimItem($lease_time = 30) {
    // Is it OK to do garbage collection here (we need to loop list of claimed
    // items)?
    $this->garbageCollection();
    $item = FALSE;

    if ($this->reserveTimeout !== NULL) {
      // A blocking version of claimItem to be used with long-running queue workers.
      $qid = $this->client->brpoplpush($this->availableListKey, $this->claimedListKey, $this->reserveTimeout);
    }
    else {
      $qid = $this->client->rpoplpush($this->availableListKey, $this->claimedListKey);
    }

    if ($qid) {
      $job = $this->client->hget($this->availableItems, $qid);
      if ($job) {
        $item = unserialize($job);
        $item->item_id ??= $item->qid;
        $this->client->setex($this->leasedKeyPrefix . $item->item_id, $lease_time, '1');
      }
    }

    return $item;
  }

  /**
   * {@inheritdoc}
   */
  public function releaseItem($item) {
    $this->client->multi();
    $this->client->lrem($this->claimedListKey, $item->item_id, -1);
    $this->client->lpush($this->availableListKey, $item->item_id);
    $this->client->exec();
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteItem($item) {
    $this->client->multi();
    $this->client->lrem($this->claimedListKey, $item->item_id, -1);
    $this->client->hdel($this->availableItems, $item->item_id);
    $this->client->exec();
  }

  /**
   * {@inheritdoc}
   */
  public function deleteQueue() {
    $keys_to_remove = [
      $this->claimedListKey,
      $this->availableListKey,
      $this->availableItems,
      $this->incrementCounterKey
    ];

    foreach ($this->client->keys($this->leasedKeyPrefix . '*') as $key) {
      $keys_to_remove[] = $key;
    }

    $this->client->del($keys_to_remove);
  }

  /**
   * Automatically release items, that have been claimed and exceeded lease time.
   */
  protected function garbageCollection() {
    foreach ($this->client->lrange($this->claimedListKey, 0, -1) as $qid) {
      if (!$this->client->exists($this->leasedKeyPrefix . $qid)) {
        // The lease expired for this ID.
        $this->client->multi();
        $this->client->lrem($this->claimedListKey, $qid, -1);
        $this->client->lpush($this->availableListKey, $qid);
        $this->client->exec();
      }
    }
  }
}
