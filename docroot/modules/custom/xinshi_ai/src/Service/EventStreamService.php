<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Service;

use Drupal\Core\Cache\CacheBackendInterface;

/**
 * 基于 cache backend 的 append-only 事件流。
 *
 * 键 xinshi_ai:events:{jobUuid},值为带递增 seq 的事件数组。
 * 默认 cache.default 走数据库,跨进程(CLI worker ↔ web SSE)可见。
 */
final class EventStreamService implements EventStreamServiceInterface {

  private const PREFIX = 'xinshi_ai:events:';
  private const TTL = 3600;
  private const POLL_USEC = 200000;

  public function __construct(
    private readonly CacheBackendInterface $cache,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function publish(string $jobUuid, string $event, array $data): void {
    $cid = self::PREFIX . $jobUuid;
    $events = $this->load($cid);
    $events[] = [
      'seq' => count($events) + 1,
      'event' => $event,
      'data' => $data,
    ];
    $this->cache->set($cid, $events, time() + self::TTL);
  }

  /**
   * {@inheritdoc}
   */
  public function read(string $jobUuid, int $cursor, int $wait = 25): array {
    $cid = self::PREFIX . $jobUuid;
    $deadline = microtime(TRUE) + $wait;

    do {
      $events = $this->load($cid);
      $fresh = array_values(array_filter(
        $events,
        static fn(array $e): bool => $e['seq'] > $cursor,
      ));
      if ($fresh) {
        return $fresh;
      }
      if (microtime(TRUE) >= $deadline) {
        return [];
      }
      usleep(self::POLL_USEC);
    } while (TRUE);
  }

  /**
   * @return array[]
   */
  private function load(string $cid): array {
    $item = $this->cache->get($cid);
    return $item ? $item->data : [];
  }

}
