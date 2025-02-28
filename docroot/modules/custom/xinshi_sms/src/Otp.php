<?php

namespace Drupal\xinshi_sms;

use Drupal\otp_login\Otp as BaseOtp;
use Drupal\sms\Direction;
use Drupal\sms\Message\SmsMessage;
use Drupal\user\Entity\User;

/**
 * Class Otp
 */
class Otp extends BaseOtp {

  /**
   * @param $mobile_number
   * @return string
   */
  public function validateMobileNumber($mobile_number) {
    $message = '';
    if (empty($mobile_number)) {
      $message = t('Please enter the phone number');
    }
    if (preg_match("/^1[3456789]\d{9}$/", $mobile_number) == 0) {
      $message = t('Please enter the correct phone number');
    }
    return $message;
  }

  /**
   * {@inheritDoc}
   */
  public function generateOtp($mobile_number) {
    $current_time = $this->currentTime->getCurrentTime();
    // Generate 6 digit random OTP number.
    $six_digit_random_number = mt_rand(100000, 999999);
    // Send OTP SMS.
    $sms = (new SmsMessage())
      // Set the message.
      ->setMessage($six_digit_random_number)
      // Set recipient phone number.
      ->addRecipient($mobile_number)
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
    $current_time = $this->currentTime->getCurrentTime();
    // Generate 6 digit random OTP number.
    $six_digit_random_number = mt_rand(100000, 999999);
    // Send OTP SMS.
    $sms = (new SmsMessage())
      // Set the message.
      ->setMessage($six_digit_random_number)
      // Set recipient phone number.
      ->addRecipient($mobile_number)
      ->setDirection(Direction::OUTGOING);

    $this->smsProvider->queue($sms);
    $this->updateUserData($uid, $mobile_number, $six_digit_random_number);
  }

  /**
   * {@inheritdoc}
   */
  public function userOtpLogin($otp, $mobile_number) {
    $user = $this->otpLoginCheckUserAlreadyExists($mobile_number);
    // Generate 6 digit random session id.
    $six_digit_random_sessionid = mt_rand(100000, 999999);
    // Activate user account if not activated.
    if (!$user->isActive()) {
      $user->set("status", 1);
      $user->save();
    }
    user_login_finalize($user);
    return $six_digit_random_sessionid;
  }

  public function sendEmailVerificationCode($email, $uid) {
    // Generate 6 digit random OTP number.
    $six_digit_random_number = mt_rand(100000, 999999);
    $this->updateUserData($uid, $email, $six_digit_random_number, 'email_user_data');
    return $six_digit_random_number;
  }

  /**
   * {@inheritdoc}
   */
  public function validateOtp($otp, $mobile_number) {
    $users = $this->entityTypeManager->getStorage('user')
      ->loadByProperties(['phone_number' => $mobile_number]);
    $user = reset($users);
    if ($user) {
      $uid = $user->id();
      return !$this->validateOtpKey($uid, $otp, $mobile_number);
    }
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
    $current_time = $this->currentTime->getCurrentTime();
    $data = $this->userData->get('xinshi_sms', $uid, $name);
    $sessions = $data['sessions'] ?? [];
    $otps = $data['otps'] ?? [];
    $otps[] = [
      'code' => $code,
      'time' => $current_time,
      'key' => $key,
    ];
    $otp_user_data = [
      "otps" => $otps,
      "last_otp_time" => $current_time,
      "sessions" => $sessions,
    ];
    $this->userData->set('xinshi_sms', $uid, $name, $otp_user_data);
  }

  /**
   * @param $uid
   * @param $code
   * @param $key
   * @param string $name
   * @return bool
   */
  public function validateOtpKey($uid, $code, $key, $name = 'otp_user_data') {
    $otp_user_data = $this->userData->get('xinshi_sms', $uid, $name);
    $current_time = $this->currentTime->getCurrentTime();
    $otps = $otp_user_data['otps'] ?? [];
    $effective = array_filter($otps, function ($otp) use ($code, $key, $current_time) {
      return $otp['code'] == $code && $otp['key'] == $key && ($current_time - $otp['time']) < 60 * 5;
    });
    $effective_otps = array_filter($otps, function ($otp) use ($code, $key, $current_time) {
      return !(($otp['code'] == $code && $otp['key'] == $key && ($current_time - $otp['time']) < 60 * 5) || (($current_time - $otp['time']) >= 60 * 5));
    });
    if ($otp_user_data) {
      $otp_user_data['otps'] = $effective_otps;
      $this->userData->set('xinshi_sms', $uid, $name, $otp_user_data);
    }
    return !empty($effective);
  }

}
