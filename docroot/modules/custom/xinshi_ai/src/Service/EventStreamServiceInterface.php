<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Service;

/**
 * SSE 进度事件的发布订阅(cache backend)。
 */
interface EventStreamServiceInterface {

  /**
   * 追加一条事件(带递增 seq)。
   */
  public function publish(string $jobUuid, string $event, array $data): void;

  /**
   * 返回 seq > $cursor 的事件;阻塞读取最长 $wait 秒。
   *
   * @return array[]
   *   每条形如 ['seq' => int, 'event' => string, 'data' => array]。
   */
  public function read(string $jobUuid, int $cursor, int $wait = 25): array;

}
