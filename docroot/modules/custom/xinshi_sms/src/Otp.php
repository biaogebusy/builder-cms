<?php

namespace Drupal\xinshi_sms;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\otp_login\Otp as BaseOtp;
use Drupal\sms\Direction;
use Drupal\sms\Message\SmsMessage;
use Drupal\sms\Provider\SmsProviderInterface;
use Drupal\user\Entity\User;
use Drupal\user\UserDataInterface;
use Drupal\xinshi_sms\Service\OtpRateLimiter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class Otp
 */
class Otp extends BaseOtp {

  /**
   * Serializes challenge updates and consumption across requests.
   */
  protected LockBackendInterface $lock;

  /**
   * Constructs the OTP service with the shared challenge lock.
   */
  public function __construct(
    TimeInterface $time,
    SmsProviderInterface $sms_provider,
    UserDataInterface $user_data,
    EntityTypeManagerInterface $entity_type_manager,
    ConfigFactory $config_factory,
    LockBackendInterface $lock,
    protected readonly OtpRateLimiter $rateLimiter,
  ) {
    parent::__construct($time, $sms_provider, $user_data, $entity_type_manager, $config_factory);
    $this->lock = $lock;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('datetime.time'),
      $container->get('sms.provider'),
      $container->get('user.data'),
      $container->get('entity_type.manager'),
      $container->get('config.factory'),
      $container->get('lock'),
      $container->get('xinshi_sms.otp_rate_limiter'),
    );
  }

  /**
   * @param $mobile_number
   * @return string
   */
  public function validateMobileNumber($mobile_number) {
    if ($mobile_number === '' || $mobile_number === NULL) {
      return t('Please enter the phone number');
    }
    // Existing clients send phone numbers as strings or JSON integers.
    if ((!is_string($mobile_number) && !is_int($mobile_number)) || !preg_match('/\A1[3-9][0-9]{9}\z/', (string) $mobile_number)) {
      return t('Please enter the correct phone number');
    }
    return '';
  }

  /**
   * {@inheritDoc}
   */
  public function generateOtp($mobile_number) {
    $this->assertMobileRecipient($mobile_number);
    $this->rateLimiter->reserveSend((string) $mobile_number);
    $current_time = $this->currentTime->getCurrentTime();
    // Generate 6 digit random OTP number.
    $six_digit_random_number = (string) random_int(100000, 999999);
    // Send OTP SMS.
    $sms = (new SmsMessage())
      // Set the message.
      ->setMessage($six_digit_random_number)
      // Set recipient phone number.
      ->addRecipient((string)$mobile_number)
      ->setDirection(Direction::OUTGOING);

    $this->smsProvider->queue($sms);
    $user = $this->otpLoginCheckUserAlreadyExists($mobile_number);
    if (!$user) {
      $disable_register = \Drupal::config('xinshi_sms.settings')->get('disable_register');
      if ($disable_register) {
        return;
      }
      /** @var User $account */
      $account = User::create();
      $account->set("name", $mobile_number);
      $account->set("phone_number", $mobile_number);
      $account->set('status', 1);
      $account->save();
      $user = $this->otpLoginCheckUserAlreadyExists($mobile_number);
    }
    $uid = $user->id();
    $this->updateUserData($uid, $mobile_number, $six_digit_random_number);
  }

  public function sendVerificationCode($mobile_number, $uid) {
    $this->assertMobileRecipient($mobile_number);
    $this->rateLimiter->reserveSend((string) $mobile_number);
    $current_time = $this->currentTime->getCurrentTime();
    // Generate 6 digit random OTP number.
    $six_digit_random_number = (string) random_int(100000, 999999);
    // Send OTP SMS.
    $sms = (new SmsMessage())
      // Set the message.
      ->setMessage($six_digit_random_number)
      // Set recipient phone number.
      ->addRecipient((string) $mobile_number)
      ->setDirection(Direction::OUTGOING);

    $this->smsProvider->queue($sms);
    $this->updateUserData($uid, $mobile_number, $six_digit_random_number);
  }

  /**
   * {@inheritdoc}
   */
  public function userOtpLogin($otp, $mobile_number) {
    $user = $this->otpLoginCheckUserAlreadyExists($mobile_number);
    if (!$user || !$user->isActive()) {
      return FALSE;
    }
    // Preserve the legacy return value; Drupal generates the actual session ID.
    $six_digit_random_sessionid = random_int(100000, 999999);
    user_login_finalize($user);
    return $six_digit_random_sessionid;
  }

  public function sendEmailVerificationCode($email, $uid) {
    if (!is_string($email) || strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
      throw new \InvalidArgumentException('Invalid verification email address.');
    }
    $this->rateLimiter->reserveSend($email, 'email');
    // Generate 6 digit random OTP number.
    $six_digit_random_number = (string) random_int(100000, 999999);
    $this->updateUserData($uid, $email, $six_digit_random_number, 'email_user_data');
    return $six_digit_random_number;
  }

  /**
   * {@inheritdoc}
   */
  public function validateOtp($otp, $mobile_number) {
    if ((!is_string($mobile_number) && !is_int($mobile_number)) || !preg_match('/\A1[3-9][0-9]{9}\z/', (string) $mobile_number)) {
      return TRUE;
    }
    // This API returns TRUE for invalid credentials, including missing users.
    return !$this->rateLimiter->verify((string) $mobile_number, function () use ($otp, $mobile_number): bool {
      $users = $this->entityTypeManager->getStorage('user')
        ->loadByProperties(['phone_number' => $mobile_number]);
      $user = reset($users);
      return $user && $user->isActive() && $this->consumeOtpKey($user->id(), $otp, $mobile_number);
    });
  }

  /**
   * {@inheritdoc}
   */
  public function validateEmailCode($otp, $uid, $email) {
    // Get OTP from database.
    return !$this->validateOtpKey($uid, $otp, $email, 'email_user_data');
  }

  public function validateOtpByUser($otp, $mobile_number, $uid) {
    return !$this->validateOtpKey($uid, $otp, $mobile_number);
  }

  /**
   * Update user otp data.
   * @param $uid
   * @param $key
   * @param $code
   * @param string $name
   */
  protected function updateUserData($uid, $key, $code, $name = 'otp_user_data') {
    $lock_name = $this->challengeLockName($uid, $name);
    if (!$this->lock->acquire($lock_name)) {
      throw new \RuntimeException('OTP challenge is busy. Please try again.');
    }
    try {
      $current_time = $this->currentTime->getCurrentTime();
      $data = $this->userData->get('xinshi_sms', $uid, $name);
      $sessions = $data['sessions'] ?? [];
      $otps = $data['otps'] ?? [];
      $otps[] = [
        'code' => (string) $code,
        'time' => $current_time,
        'key' => (string) $key,
      ];
      $otp_user_data = [
        "otps" => $otps,
        "last_otp_time" => $current_time,
        "sessions" => $sessions,
      ];
      $this->userData->set('xinshi_sms', $uid, $name, $otp_user_data);
    }
    finally {
      $this->lock->release($lock_name);
    }
  }

  /**
   * @param $uid
   * @param $code
   * @param $key
   * @param string $name
   * @return bool
   */
  public function validateOtpKey($uid, $code, $key, $name = 'otp_user_data') {
    if ((!is_string($key) && !is_int($key)) || strlen((string) $key) > 254) {
      return FALSE;
    }
    $channel = $name === 'email_user_data' ? 'email' : 'sms';
    return $this->rateLimiter->verify((string) $key,
      fn(): bool => $this->consumeOtpKey($uid, $code, $key, $name), $channel);
  }

  /**
   * Compares and consumes only inside the shared verification budget.
   */
  private function consumeOtpKey($uid, $code, $key, $name = 'otp_user_data'): bool {
    if (!is_string($code) || !preg_match('/\A[0-9]{6}\z/', $code) || (!is_string($key) && !is_int($key))) {
      return FALSE;
    }
    $lock_name = $this->challengeLockName($uid, $name);
    if (!$this->lock->acquire($lock_name)) {
      return FALSE;
    }
    try {
      $otp_user_data = $this->userData->get('xinshi_sms', $uid, $name);
      $current_time = $this->currentTime->getCurrentTime();
      $remaining = [];
      $valid = FALSE;
      foreach ($otp_user_data['otps'] ?? [] as $otp) {
        $age = $current_time - ($otp['time'] ?? 0);
        if ($age < 0 || $age >= 300) {
          continue;
        }
        // Older records may store integer codes or phone numbers.
        $stored_code = $otp['code'] ?? NULL;
        $stored_key = $otp['key'] ?? NULL;
        if ((is_string($stored_code) || is_int($stored_code)) && (is_string($stored_key) || is_int($stored_key))
          && (string) $stored_key === (string) $key && hash_equals((string) $stored_code, $code)) {
          $valid = TRUE;
        }
        else {
          $remaining[] = $otp;
        }
      }
      if ($otp_user_data) {
        $otp_user_data['otps'] = $remaining;
        $this->userData->set('xinshi_sms', $uid, $name, $otp_user_data);
      }
      return $valid;
    }
    finally {
      $this->lock->release($lock_name);
    }
  }

  /**
   * Locks the entire user-data record to avoid lost updates across recipients.
   */
  protected function challengeLockName($uid, string $name): string {
    return 'xinshi_sms.challenge.' . hash('sha256', $uid . ':' . $name);
  }

  /**
   * Reject malformed recipients before reserving a send or causing side effects.
   */
  private function assertMobileRecipient(mixed $mobile_number): void {
    if ((!is_string($mobile_number) && !is_int($mobile_number))
      || !preg_match('/\A1[3-9][0-9]{9}\z/', (string) $mobile_number)) {
      throw new \InvalidArgumentException('Invalid verification phone number.');
    }
  }
}
