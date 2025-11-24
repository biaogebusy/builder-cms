<?php

namespace Drupal\redis\Queue;

use Drupal\Core\Queue\QueueInterface;
use Drupal\redis\ClientInterface;

/**
 * Redis queue implementation using PhpRedis extension backend.
 *
 * @ingroup queue
 */
class RedisQueue implements QueueInterface {

  /**
   * The Redis client.
   */
  protected ClientInterface $client;

  /**
   * Prefix used with all keys.
   */
  const KEY_PREFIX = 'drupal:queue:';

  /**
   * The name of the queue this instance is working with.
   *
   * @var string
   */
  protected $name;

  /**
   * Key for list of available items.
   *
   * @var string
   */
  protected $availableListKey;

  /**
   * Key for list of claimed items.
   *
   * @var string
   */
  protected $claimedListKey;

  /**
   * Key prefix for items that are used to track expiration of leased items.
   *
   * @var string
   */
  protected $leasedKeyPrefix;

  /**
   * Key of increment counter key.
   *
   * @var string
   */
  protected $incrementCounterKey;

  /**
   * Key for hash table of available queue items.
   *
   * @var string
   */
  protected $availableItems;

  /**
   * Reserve timeout for blocking item claim.
   *
   * This will be set to number of seconds to wait for an item to be claimed.
   * Non-blocking approach will be used when set to NULL.
   *
   * @var int|null
   */
  protected $reserveTimeout;

  /**
   * Constructs a \Drupal\redis\Queue\PhpRedis object.
   *
   * @param string $name
   *   The name of the queue.
   * @param array $settings
   *   Array of Redis-related settings for this queue.
   * @param \Redis $client
   *   The PhpRedis client.
   */
  public function __construct($name, array $settings, ClientInterface $client) {
    $this->name = $name;
    $this->reserveTimeout = $settings['reserve_timeout'];
    $this->availableListKey = static::KEY_PREFIX . $name . ':avail';
    $this->availableItems = static::KEY_PREFIX . $name . ':items';
    $this->claimedListKey = static::KEY_PREFIX . $name . ':claimed';
    $this->leasedKeyPrefix = static::KEY_PREFIX . $name . ':lease:';
    $this->incrementCounterKey = static::KEY_PREFIX . $name . ':counter';
    $this->client = $client;

    $this->client->addIgnorePattern(static::KEY_PREFIX . $name . ':*');
  }

  /**
   * {@inheritdoc}
   */
  public function createQueue() {
    // Nothing to do here.
  }

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

    if (!$this->client->hsetnx($this->availableItems, $record->item_id, serialize($record))) {
      return FALSE;
    }

    $start_len = $this->client->lLen($this->availableListKey);
    if ($start_len < $this->client->lpush($this->availableListKey, $record->item_id)) {
      return $record->item_id;
    }

    return FALSE;
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
    $this->client->lrem($this->claimedListKey, $item->item_id, -1);
    $this->client->lpush($this->availableListKey, $item->item_id);
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteItem($item) {
    $this->client->lrem($this->claimedListKey, $item->item_id, -1);
    $this->client->hdel($this->availableItems, $item->item_id);
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
        $this->client->lrem($this->claimedListKey, $qid, -1);
        $this->client->lpush($this->availableListKey, $qid);
      }
    }
  }
}
