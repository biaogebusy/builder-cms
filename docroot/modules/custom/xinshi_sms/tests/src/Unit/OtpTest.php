<?php

namespace Drupal\Tests\xinshi_sms\Unit;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\sms\Provider\SmsProviderInterface;
use Drupal\user\UserDataInterface;
use Drupal\user\UserInterface;
use Drupal\xinshi_sms\Otp;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Exercises real OTP logic using in-memory persistence and no external sends.
 */
final class OtpTest extends TestCase {

  private array $data;
  private bool $lockAvailable = TRUE;
  private bool $active = TRUE;
  private bool $exists = TRUE;
  private int $now = 1000;
  private Otp $otp;

  protected function setUp(): void {
    $GLOBALS['xinshi_sms_test_logins'] = [];
    $this->data = ['otps' => [['code' => '123456', 'key' => '13800000000', 'time' => 1000]]];
    $time = $this->createMock(TimeInterface::class);
    $time->method('getCurrentTime')->willReturnCallback(fn() => $this->now);
    $data = $this->createMock(UserDataInterface::class);
    $data->method('get')->willReturnCallback(fn() => $this->data);
    $data->method('set')->willReturnCallback(function ($module, $uid, $name, $value) {
      $this->data = $value;
    });
    $user = $this->createMock(UserInterface::class);
    $user->method('id')->willReturn(7);
    $user->method('isActive')->willReturnCallback(fn() => $this->active);
    $user->expects($this->never())->method('set');
    $user->expects($this->never())->method('save');
    $storage = $this->createMock(EntityStorageInterface::class);
    $storage->method('loadByProperties')->willReturnCallback(fn() => $this->exists ? [$user] : []);
    $entities = $this->createMock(EntityTypeManagerInterface::class);
    $entities->method('getStorage')->with('user')->willReturn($storage);
    $lock = $this->createMock(LockBackendInterface::class);
    $lock->method('acquire')->willReturnCallback(fn() => $this->lockAvailable);
    $this->otp = new Otp($time, $this->createMock(SmsProviderInterface::class), $data, $entities, $this->createMock(ConfigFactory::class), $lock);
  }

  public static function invalidCodes(): array {
    return array_map(fn($code) => [$code], [TRUE, FALSE, NULL, [], ['123456'], 123456, 123456.0, '', '12345', '1234567', "123456\n", ' 123456', '1e0005', '654321']);
  }

  #[DataProvider('invalidCodes')]
  public function testRejectsInvalidCodeWithoutConsumingValidChallenge(mixed $code): void {
    $this->assertFalse($this->otp->validateOtpKey(7, $code, '13800000000'));
    $this->assertTrue($this->otp->validateOtpKey(7, '123456', '13800000000'));
  }

  public function testChallengeCanOnlyBeConsumedOnce(): void {
    $this->assertFalse($this->otp->validateOtp('123456', '13800000000'));
    $this->assertTrue($this->otp->validateOtp('123456', '13800000000'));
  }

  public function testLegacyIntegerStorageIsAcceptedForStringInput(): void {
    $this->data['otps'][0]['code'] = 123456;
    $this->assertTrue($this->otp->validateOtpKey(7, '123456', '13800000000'));
  }

  public function testWrongRecipientCannotConsumeChallenge(): void {
    $this->assertFalse($this->otp->validateOtpKey(7, '123456', TRUE));
    $this->assertFalse($this->otp->validateOtpKey(7, '123456', '13900000000'));
    $this->assertTrue($this->otp->validateOtpKey(7, '123456', '13800000000'));
  }

  public function testExpiredAndFutureChallengesAreRejected(): void {
    $this->now = 1300;
    $this->assertFalse($this->otp->validateOtpKey(7, '123456', '13800000000'));
    $this->data['otps'] = [['code' => '123456', 'key' => '13800000000', 'time' => 1400]];
    $this->assertFalse($this->otp->validateOtpKey(7, '123456', '13800000000'));
  }

  public function testConcurrentConsumerFailsClosed(): void {
    $this->lockAvailable = FALSE;
    $this->assertFalse($this->otp->validateOtpKey(7, '123456', '13800000000'));
    $this->lockAvailable = TRUE;
    $this->assertTrue($this->otp->validateOtpKey(7, '123456', '13800000000'));
  }

  public function testBlockedAccountCannotValidateOrLogIn(): void {
    $this->active = FALSE;
    $this->assertTrue($this->otp->validateOtp('123456', '13800000000'));
    $this->assertFalse($this->otp->userOtpLogin('123456', '13800000000'));
    $this->assertSame([], $GLOBALS['xinshi_sms_test_logins']);
  }

  public function testMissingAccountFailsClosed(): void {
    $this->exists = FALSE;
    $this->assertTrue($this->otp->validateOtp('123456', '13800000000'));
    $this->assertFalse($this->otp->userOtpLogin('123456', '13800000000'));
    $this->assertSame([], $GLOBALS['xinshi_sms_test_logins']);
  }

  public function testActiveAccountCanLogInAfterValidation(): void {
    $this->assertFalse($this->otp->validateOtp('123456', '13800000000'));
    $this->assertIsInt($this->otp->userOtpLogin('123456', '13800000000'));
    $this->assertSame([7], $GLOBALS['xinshi_sms_test_logins']);
  }

  public function testIntegerPhoneAndLegacyStorageRemainCompatible(): void {
    $this->data['otps'][0]['key'] = 13800000000;
    $this->assertFalse($this->otp->validateOtp('123456', 13800000000));
  }

  public function testInvalidPhoneTypesFailClosed(): void {
    foreach ([TRUE, FALSE, NULL, [], new \stdClass(), 13800000000.0] as $phone) {
      $this->assertTrue($this->otp->validateOtp('123456', $phone));
    }
  }

  public function testNewEmailChallengeUsesStringStorageAndSingleUse(): void {
    $code = $this->otp->sendEmailVerificationCode('test@example.invalid', 7);
    $this->assertMatchesRegularExpression('/\A[0-9]{6}\z/', $code);
    $this->assertSame($code, $this->data['otps'][1]['code']);
    $this->assertFalse($this->otp->validateEmailCode($code, 7, 'test@example.invalid'));
    $this->assertTrue($this->otp->validateEmailCode($code, 7, 'test@example.invalid'));
  }

}

namespace Drupal\xinshi_sms;

/**
 * Records session establishment without opening a real Drupal session.
 */
function user_login_finalize($account): void {
  $GLOBALS['xinshi_sms_test_logins'][] = $account->id();
}
