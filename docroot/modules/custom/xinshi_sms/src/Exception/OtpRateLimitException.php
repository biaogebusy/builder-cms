<?php

declare(strict_types=1);

namespace Drupal\xinshi_sms\Exception;

/**
 * A shared OTP limit was reached, or its atomic check is busy.
 */
final class OtpRateLimitException extends \RuntimeException {

  public function __construct(public readonly int $retryAfter) {
    parent::__construct('Too many verification code requests. Please try again later.');
  }

}
