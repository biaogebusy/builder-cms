<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Service;

/**
 * SSE 访问票据:绑定 jobUuid + uid,自包含签名,不存库。
 */
interface EventTicketServiceInterface {

  /**
   * 签发票据(默认 5min TTL)。
   */
  public function issue(string $jobUuid, int $uid, ?int $ttl = NULL): string;

  /**
   * 校验票据:验签 + 比对 jobUuid + 未过期。
   */
  public function verify(string $ticket, string $jobUuid): bool;

}
