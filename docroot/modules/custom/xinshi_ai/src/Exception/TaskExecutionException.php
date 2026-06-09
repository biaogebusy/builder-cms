<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Exception;

/**
 * 任务执行失败,携带归一化错误码(供 worker 决定重试 / markFailed)。
 */
final class TaskExecutionException extends \RuntimeException {

  public function __construct(
    string $message,
    public readonly string $errorCode,
    ?\Throwable $previous = NULL,
  ) {
    parent::__construct($message, 0, $previous);
  }

}
