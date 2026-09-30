<?php

declare(strict_types=1);

namespace Drupal\xinshi_sms\Service;

use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\xinshi_sms\Exception\OtpRateLimitException;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Serializes shared OTP budgets across REST, forms and direct service callers.
 */
class OtpRateLimiter {

  public const SEND_WINDOW = 3600;
  public const SEND_RECIPIENT_LIMIT = 5;
  public const SEND_IP_LIMIT = 20;
  public const VERIFY_WINDOW = 300;
  public const VERIFY_RECIPIENT_LIMIT = 5;
  public const VERIFY_IP_WINDOW = 3600;
  public const VERIFY_IP_LIMIT = 50;

  public function __construct(
    private readonly FloodInterface $flood,
    private readonly LockBackendInterface $lock,
    private readonly RequestStack $requests,
  ) {}

  /**
   * Reserves before queueing or account writes, including failed send attempts.
   */
  public function reserveSend(string $recipient, string $channel = 'sms'): void {
    $ip = $this->clientIp();
    // Keep the existing REST event names and phone identifiers on upgrade.
    $event = $channel === 'sms' ? 'xinshi_sms.otp_generate_phone' : 'xinshi_sms.otp_generate_email';
    $key = $channel === 'sms' ? $recipient : hash('sha256', strtolower($recipient));
    $this->locked('send', $event . ':' . $key, $ip, function () use ($event, $key, $ip): void {
      if (!$this->flood->isAllowed($event, self::SEND_RECIPIENT_LIMIT, self::SEND_WINDOW, $key)
        || !$this->flood->isAllowed('xinshi_sms.otp_generate_ip', self::SEND_IP_LIMIT, self::SEND_WINDOW, $ip)) {
        throw new OtpRateLimitException(self::SEND_WINDOW);
      }
      $this->flood->register($event, self::SEND_WINDOW, $key);
      $this->flood->register('xinshi_sms.otp_generate_ip', self::SEND_WINDOW, $ip);
    });
  }

  /**
   * Limits failures atomically with the actual challenge comparison/consumption.
   *
   * Successful verification clears only this recipient's failures. IP failures
   * remain, so a caller cannot reset a spraying budget with one known account.
   */
  public function verify(string $recipient, callable $validate, string $channel = 'sms'): bool {
    $key = hash('sha256', $channel . ':' . ($channel === 'email' ? strtolower($recipient) : $recipient));
    $ip = $this->clientIp();
    return $this->locked('verify', $key, $ip, function () use ($key, $ip, $validate): bool {
      if (!$this->flood->isAllowed('xinshi_sms.otp_verify_ip', self::VERIFY_IP_LIMIT, self::VERIFY_IP_WINDOW, $ip)) {
        throw new OtpRateLimitException(self::VERIFY_IP_WINDOW);
      }
      if (!$this->flood->isAllowed('xinshi_sms.otp_verify_recipient', self::VERIFY_RECIPIENT_LIMIT, self::VERIFY_WINDOW, $key)) {
        throw new OtpRateLimitException(self::VERIFY_WINDOW);
      }
      $valid = $validate();
      if ($valid) {
        $this->flood->clear('xinshi_sms.otp_verify_recipient', $key);
      }
      else {
        $this->flood->register('xinshi_sms.otp_verify_recipient', self::VERIFY_WINDOW, $key);
        $this->flood->register('xinshi_sms.otp_verify_ip', self::VERIFY_IP_WINDOW, $ip);
      }
      return $valid;
    });
  }

  /**
   * Holds both dimensions through check-and-register, releasing on every exit.
   */
  private function locked(string $operation, string $recipient, string $ip, callable $action): mixed {
    $names = [
      'xinshi_sms.rate.' . $operation . '.ip.' . hash('sha256', $ip),
      'xinshi_sms.rate.' . $operation . '.recipient.' . hash('sha256', $recipient),
    ];
    $acquired = [];
    try {
      foreach ($names as $name) {
        if (!$this->lock->acquire($name, 30.0)) {
          throw new OtpRateLimitException(1);
        }
        $acquired[] = $name;
      }
      return $action();
    }
    finally {
      foreach (array_reverse($acquired) as $name) {
        $this->lock->release($name);
      }
    }
  }

  private function clientIp(): string {
    // Use Drupal/Symfony's trusted-proxy policy, never raw forwarding headers.
    // Non-HTTP callers share a bucket rather than bypassing the IP budget.
    return $this->requests->getCurrentRequest()?->getClientIp() ?? 'no-client-ip';
  }

}
