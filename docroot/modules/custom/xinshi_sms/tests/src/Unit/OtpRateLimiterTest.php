<?php

namespace Drupal\Tests\xinshi_sms\Unit;

use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\xinshi_sms\Exception\OtpRateLimitException;
use Drupal\xinshi_sms\Service\OtpRateLimiter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Shared budgets, cleanup, expiry and atomic admission without sending messages.
 */
final class OtpRateLimiterTest extends TestCase {

  private MemoryOtpFlood $flood;
  private RequestStack $requests;
  private OtpRateLimiter $limiter;
  private array $held = [];
  private LockBackendInterface $lock;

  protected function setUp(): void {
    $this->flood = new MemoryOtpFlood();
    $this->requests = new RequestStack();
    $this->setIp('192.0.2.1');
    $this->lock = $this->createMock(LockBackendInterface::class);
    $this->lock->method('acquire')->willReturnCallback(function ($name): bool {
      if (isset($this->held[$name])) {
        return FALSE;
      }
      return $this->held[$name] = TRUE;
    });
    $this->lock->method('release')->willReturnCallback(function ($name): void { unset($this->held[$name]); });
    $this->limiter = new OtpRateLimiter($this->flood, $this->lock, $this->requests);
  }

  protected function tearDown(): void {
    $this->assertSame([], $this->held, 'Every acquired lock must be released.');
  }

  public function testSendBudgetIsSharedAcrossInstancesAndClientIps(): void {
    $other = new OtpRateLimiter($this->flood, $this->lock, $this->requests);
    for ($i = 0; $i < 5; $i++) {
      $this->setIp('192.0.2.' . ($i + 1));
      ($i % 2 ? $other : $this->limiter)->reserveSend('13800000000');
    }
    $this->setIp('192.0.2.100');
    $this->expectException(OtpRateLimitException::class);
    $other->reserveSend('13800000000');
  }

  public function testRotatingRecipientsCannotBypassIpSendBudget(): void {
    for ($i = 0; $i < 20; $i++) {
      $this->limiter->reserveSend((string) (13800000000 + $i));
    }
    $this->expectException(OtpRateLimitException::class);
    $this->limiter->reserveSend('13900000000');
  }

  public function testSendWindowExpiresWithoutWaitingForCron(): void {
    for ($i = 0; $i < 5; $i++) {
      $this->limiter->reserveSend('13800000000');
    }
    $this->flood->now += 3600;
    $this->limiter->reserveSend('13800000000');
    $this->assertCount(6, $this->flood->events['xinshi_sms.otp_generate_phone']['13800000000']);
  }

  public function testFailedVerificationBlocksComparisonAndDoesNotExtendTheWindow(): void {
    for ($i = 0; $i < 5; $i++) {
      $this->assertFalse($this->limiter->verify('13800000000', fn() => FALSE));
    }
    $this->flood->now += 299;
    try {
      $this->limiter->verify('13800000000', function () { $this->fail('Throttled comparisons must not run.'); });
      $this->fail('Expected throttling.');
    }
    catch (OtpRateLimitException $e) {
      $this->assertSame(300, $e->retryAfter);
    }
    $this->flood->now++;
    $this->assertTrue($this->limiter->verify('13800000000', fn() => TRUE));
  }

  public function testSuccessClearsOnlyRecipientFailures(): void {
    $this->limiter->reserveSend('13800000000');
    for ($i = 0; $i < 4; $i++) {
      $this->limiter->verify('13800000000', fn() => FALSE);
    }
    $this->assertTrue($this->limiter->verify('13800000000', fn() => TRUE));
    $this->assertSame([], $this->flood->events['xinshi_sms.otp_verify_recipient']);
    $this->assertCount(4, $this->flood->events['xinshi_sms.otp_verify_ip']['192.0.2.1']);
    $this->assertCount(1, $this->flood->events['xinshi_sms.otp_generate_phone']['13800000000']);
    for ($i = 0; $i < 5; $i++) {
      $this->assertFalse($this->limiter->verify('13800000000', fn() => FALSE));
    }
  }

  public function testIpBudgetSurvivesSuccessAndChangingRecipients(): void {
    for ($i = 0; $i < 50; $i++) {
      $this->limiter->verify((string) (13800000000 + $i), fn() => FALSE);
      if ($i < 49) {
        $this->assertTrue($this->limiter->verify('13900000000', fn() => TRUE));
      }
    }
    $this->expectException(OtpRateLimitException::class);
    $this->limiter->verify('13900000001', function () { $this->fail('IP limit must precede comparison.'); });
  }

  public function testResendingCannotResetVerificationFailures(): void {
    for ($i = 0; $i < 5; $i++) {
      $this->limiter->verify('13800000000', fn() => FALSE);
    }
    $this->limiter->reserveSend('13800000000');
    $this->expectException(OtpRateLimitException::class);
    $this->limiter->verify('13800000000', fn() => TRUE);
  }

  public function testConcurrentVerificationFailsBeforeItsCallback(): void {
    $other = new OtpRateLimiter($this->flood, $this->lock, $this->requests);
    $this->assertFalse($this->limiter->verify('13800000000', function () use ($other) {
      try {
        $other->verify('13800000000', function () { $this->fail('Concurrent comparison must not run.'); });
        $this->fail('Expected lock rejection.');
      }
      catch (OtpRateLimitException $e) {
        $this->assertSame(1, $e->retryAfter);
      }
      return FALSE;
    }));
    $this->assertCount(1, $this->flood->events['xinshi_sms.otp_verify_ip']['192.0.2.1']);
  }

  public function testConcurrentSendCannotSpendTheSameRemainingSlot(): void {
    $backend = $this->createMock(FloodInterface::class);
    $other = new OtpRateLimiter($this->flood, $this->lock, $this->requests);
    $backend->method('isAllowed')->willReturnCallback(function () use ($other) {
      try {
        $other->reserveSend('13800000000');
        $this->fail('A concurrent send must not reserve while locks are held.');
      }
      catch (OtpRateLimitException $e) {
        $this->assertSame(1, $e->retryAfter);
      }
      return TRUE;
    });
    $backend->expects($this->exactly(2))->method('register');
    (new OtpRateLimiter($backend, $this->lock, $this->requests))->reserveSend('13800000000');
    $this->assertSame([], $this->flood->events);
  }

  public function testStorageFailureReleasesLocksAndCannotRunVerification(): void {
    $backend = $this->createMock(FloodInterface::class);
    $backend->method('isAllowed')->willThrowException(new \RuntimeException('storage unavailable'));
    $this->expectException(\RuntimeException::class);
    (new OtpRateLimiter($backend, $this->lock, $this->requests))->verify('13800000000', function () {
      $this->fail('Comparison must not run without a working limiter.');
    });
  }

  public function testUntrustedForwardedHeaderCannotSelectIpBucket(): void {
    $this->requests->getCurrentRequest()->headers->set('X-Forwarded-For', '192.0.2.200');
    $this->limiter->reserveSend('13800000000');
    $this->assertSame(['192.0.2.1'], array_keys($this->flood->events['xinshi_sms.otp_generate_ip']));
  }

  public function testEmailCasingAndMissingRequestDoNotBypassBudgets(): void {
    $this->requests->pop();
    for ($i = 0; $i < 5; $i++) {
      $this->limiter->reserveSend($i % 2 ? 'USER@EXAMPLE.INVALID' : 'user@example.invalid', 'email');
    }
    $this->assertCount(5, $this->flood->events['xinshi_sms.otp_generate_ip']['no-client-ip']);
    $this->expectException(OtpRateLimitException::class);
    $this->limiter->reserveSend('user@example.invalid', 'email');
  }

  private function setIp(string $ip): void {
    $this->requests->pop();
    $this->requests->push(Request::create('/', server: ['REMOTE_ADDR' => $ip]));
  }

}
