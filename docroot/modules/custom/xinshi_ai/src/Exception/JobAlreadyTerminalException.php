<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai\Exception;

/**
 * A state change was refused because the image job already ended.
 *
 * Raised when an asset would be committed to a job that was cancelled (or
 * otherwise finished) while the provider call was running: the late image is
 * kept as evidence but never becomes a delivery the user is charged for.
 */
final class JobAlreadyTerminalException extends \RuntimeException {

  public function __construct(
    public readonly string $jobUuid,
    public readonly string $status,
  ) {
    parent::__construct(sprintf('Image job %s is already %s.', $jobUuid, $status));
  }

}
