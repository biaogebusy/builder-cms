<?php

namespace Drupal\otp_login;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user\Entity\User;
use Drupal\sms\Message\SmsMessage;
use Drupal\sms\Direction;
use Drupal\Component\Datetime\TimeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\sms\Provider\SmsProviderInterface;
use Drupal\user\UserDataInterface;
use function GuzzleHttp\Psr7\str;
use Drupal\Core\Config\ConfigFactory;

/**
 * Class Otp.
 */
class Otp {

  /**
   * A date time instance.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $currentTime;

  /**
   * The SMS Provider.
   *
   * @var \Drupal\sms\Provider\SmsProviderInterface
   */
  protected $smsProvider;

  /**
   * The user data service.
   *
   * @var \Drupal\user\UserDataInterface
   */
  protected $userData;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Configuration Factory.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $configFactory;

  protected $cipherMethod;
  protected $separator;
  protected $ivLength;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('datetime.time'),
      $container->get('sms.provider'),
      $container->get('user.data'),
      $container->get('entity_type.manager'),
      $container->get('config.factory')
    );
  }

  /**
   * Creates an object.
   *
   * @param Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   * @param \Drupal\sms\Provider\SmsProviderInterface $sms_provider
   *   The SMS service provider.
   * @param \Drupal\user\UserDataInterface $user_data
   *   The user data service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_manager
   *   The entity type manager.
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   The config factory.
   */
  public function __construct(TimeInterface $time, SmsProviderInterface $sms_provider, UserDataInterface $user_data, EntityTypeManagerInterface $entity_type_manager, ConfigFactory $config_factory) {
    $this->currentTime = $time;
    $this->smsProvider = $sms_provider;
    $this->userData = $user_data;
    $this->entityTypeManager = $entity_type_manager;
    $this->config_factory = $config_factory;
    $this->cipherMethod = 'AES-256-CBC';
    $this->separator = '::';
    $this->ivLength = openssl_cipher_iv_length($this->cipherMethod);
  }

  /**
   * {@inheritdoc}
   */
  public function generateOtp($mobile_number) {
    $current_time = $this->currentTime->getCurrentTime();
    // Generate 6 digit random OTP number.
    $six_digit_random_number = mt_rand(100000, 999999);
    // Send OTP SMS.
    $sms = (new SmsMessage())
      // Set the message.
      ->setMessage('Your OTP is: ' . $six_digit_random_number)
      // Set recipient phone number.
      ->addRecipient($mobile_number)
      ->setDirection(Direction::OUTGOING);

    $this->smsProvider->queue($sms);

    $user = $this->otpLoginCheckUserAlreadyExists($mobile_number);
    if (!$user) {
      // Create user object.
      $account = User::create();
      $account->set("name", $mobile_number);
      $account->set("phone_number", $mobile_number);
      $account->save();
      $user = $this->otpLoginCheckUserAlreadyExists($mobile_number);
    }
    $uid = $user->id();

    $data = $this->userData->get('otp_login', $uid, 'otp_user_data');
    $sessions = $data['sessions'];
    if ($data['otps']) {
      $last_otp_key = end(array_keys($data['otps']));
      $new_otp_key = $last_otp_key + 1;
      $otps = array_merge($data['otps'], [$new_otp_key => ['otp' => $six_digit_random_number, 'otp_time' => $current_time]]);
    }
    else {
      $otps = [['otp' => $six_digit_random_number, 'otp_time' => $current_time]];
    }
    $otp_user_data = [
      "mobile_number" => $mobile_number,
      "otps" => $otps,
      "last_otp_time" => $current_time,
      "sessions" => $sessions,
    ];
    $this->userData->set('otp_login', $uid, 'otp_user_data', $otp_user_data);
  }

  /**
   * {@inheritdoc}
   */
  public function otpLoginCheckUserAlreadyExists($phone_number) {
    if (empty($phone_number)) {
      return;
    }

    $accounts = $this->entityTypeManager->getStorage('user')->loadByProperties(['phone_number' => $phone_number]);
    $account = reset($accounts);
    return $account;
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
    }
    // Get OTP from database.
    $data = $this->userData->get('otp_login', $uid, 'otp_user_data');
    if (!empty($mobile_number) && !empty($otp)) {
      if (array_search($otp, array_column($data['otps'], 'otp')) === FALSE) {
        $otp_incorrect = TRUE;
        return $otp_incorrect;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function userOtpLogin($otp, $mobile_number) {
    $user = $this->otpLoginCheckUserAlreadyExists($mobile_number);
    $uid = $user->id();
    // Generate 6 digit random session id.
    $six_digit_random_sessionid = mt_rand(100000, 999999);

    // Activate user account if not activated.
    if (!$user->isActive()) {
      $user->set("status", 1);
      $user->save();
    }
    $data = $this->userData->get('otp_login', $uid, 'otp_user_data');
    $lastotptime = $data['last_otp_time'];
    if (($key = array_search($otp, array_column($data['otps'], 'otp'))) !== FALSE) {
      unset($data['otps'][$key]);
      $otps = array_values($data['otps']);
    }
    if ($data['sessions']) {
      $saved_sessions = $data['sessions'];
      $last_session_key = end(array_keys($saved_sessions));
      $new_session_key = $last_session_key + 1;
      $six_digit_sessionid_with_key = [$new_session_key => $six_digit_random_sessionid];
      $sessions = array_merge($saved_sessions, $six_digit_sessionid_with_key);
    }
    else {
      $sessions = [$six_digit_random_sessionid];
    }
    $session_user_data = [
      "mobile_number" => $mobile_number,
      "otps" => $otps,
      "last_otp_time" => $lastotptime,
      "sessions" => $sessions,
    ];
    $this->userData->set('otp_login', $uid, 'otp_user_data', $session_user_data);

    // Login programmatically as a user.
    $user = $this->entityTypeManager->getStorage('user')->load($uid);
    user_login_finalize($user);

    return $six_digit_random_sessionid;
  }

  /**
   * {@inheritdoc}
   */
  public function validateSessionid($session_id, $mobile_number) {
    $users = $this->entityTypeManager->getStorage('user')
      ->loadByProperties(['phone_number' => $mobile_number]);
    $user = reset($users);
    if ($user) {
      $uid = $user->id();
    }
    // Get sessions from database.
    $data = $this->userData->get('otp_login', $uid, 'otp_user_data');

    if (!empty($mobile_number) && !empty($session_id)) {
      if (!in_array($session_id, $data['sessions'])) {
        $session_id_incorrect = TRUE;
        return $session_id_incorrect;
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function userOtpLogout($session_id, $mobile_number) {
    $users = $this->entityTypeManager->getStorage('user')
      ->loadByProperties(['phone_number' => $mobile_number]);
    $user = reset($users);
    if ($user) {
      $uid = $user->id();
    } else {
      return;
    }
    $data = $this->userData->get('otp_login', $uid, 'otp_user_data');
    $lastotptime = $data['last_otp_time'];
    if (($key = array_search($session_id, $data['sessions'] ?? [])) !== FALSE) {
      unset($data['sessions'][$key]);
      $sessions = array_values($data['sessions']);
    }
    else {
      $sessions = $data['sessions'];
    }
    $session_user_data = [
      "mobile_number" => $mobile_number,
      "otps" => $data['otps'],
      "last_otp_time" => $lastotptime,
      "sessions" => $sessions,
    ];
    $this->userData->set('otp_login', $uid, 'otp_user_data', $session_user_data);
  }

  /**
   * {@inheritdoc}
   */
  public function generateTiniyoOtp($mobile_number) {
    $config = $this->config_factory->get('otp_login.settings');
    $base_uri = 'https://api.tiniyo.com/v1/Account/';
    $client = \Drupal::httpClient();
    $tiniyo_auth_id = $config->get('tiniyo_authid');
    $tiniyo_auth_secret_id = $config->get('tiniyo_authsecretid');
    $tiniyo_otp_channel = $config->get('tiniyo_otp_channel');
    $tiniyo_otp_length = $config->get('tiniyo_otp_length');

    $secret = $config->get('encryption_secret_key');

    $decodedKey = base64_decode($secret);
    list($tiniyo_auth_id, $authid_iv) = explode($this->separator, base64_decode($tiniyo_auth_id), 2);
    list($tiniyo_auth_secret_id, $authsecretid_iv) = explode($this->separator, base64_decode($tiniyo_auth_secret_id), 2);

    $authid_iv = substr($authid_iv, 0, $this->ivLength);
    $authsecretid_iv = substr($authsecretid_iv, 0, $this->ivLength);

    // Descrypt the string.
    $decrypted_tiniyo_authid = openssl_decrypt($tiniyo_auth_id, $this->cipherMethod, $decodedKey, 0, $authid_iv);
    $decrypted_tiniyo_authsecretid = openssl_decrypt($tiniyo_auth_secret_id, $this->cipherMethod, $decodedKey, 0, $authsecretid_iv);

    $settings = [
      'auth' => [$decrypted_tiniyo_authid, $decrypted_tiniyo_authsecretid],
      'headers' => [
        'Content-Type' => 'application/json',
      ],
      'json' => [
        'channel' => $tiniyo_otp_channel,
        'length' => $tiniyo_otp_length,
        'dst' => $mobile_number,
      ],
    ];

    $url = $base_uri . $decrypted_tiniyo_authid . '/Verifications';
    try {
      $request = str($client->post($url, $settings));
      $current_time = $this->currentTime->getCurrentTime();
      $user = $this->otpLoginCheckUserAlreadyExists($mobile_number);
      if (!$user) {
        // Create user object.
        $account = User::create();
        $account->set("name", $mobile_number);
        $account->save();
        $user = $this->otpLoginCheckUserAlreadyExists($mobile_number);
      }
      $uid = $user->id();

      $data = $this->userData->get('otp_login', $uid, 'otp_user_data');
      $sessions = $data['sessions'];
      $otp_user_data = [
        "mobile_number" => $mobile_number,
        "otps" => [],
        "last_otp_time" => $current_time,
        "sessions" => $sessions,
      ];
      $this->userData->set('otp_login', $uid, 'otp_user_data', $otp_user_data);
    }
    catch (GuzzleException $error) {
      $response = $error->getResponse();
      $response_info = json_decode($response->getBody()->getContents());
      $this->messenger()->addError($this->t('%error: %message', ['%error' => $response_info->status, '%message' => $response_info->message]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function userTiniyoOtpLogin($mobile_number) {
    $user = $this->otpLoginCheckUserAlreadyExists($mobile_number);
    $uid = $user->id();
    // Generate 6 digit random session id.
    $six_digit_random_sessionid = mt_rand(100000, 999999);

    // Activate user account if not activated.
    if (!$user->isActive()) {
      $user->set("status", 1);
      $user->save();
    }
    $data = $this->userData->get('otp_login', $uid, 'otp_user_data');
    $lastotptime = $data['last_otp_time'];
    if ($data['sessions']) {
      $saved_sessions = $data['sessions'];
      $last_session_key = end(array_keys($saved_sessions));
      $new_session_key = $last_session_key + 1;
      $six_digit_sessionid_with_key = [$new_session_key => $six_digit_random_sessionid];
      $sessions = array_merge($saved_sessions, $six_digit_sessionid_with_key);
    }
    else {
      $sessions = [$six_digit_random_sessionid];
    }
    $session_user_data = [
      "mobile_number" => $mobile_number,
      "otps" => [],
      "last_otp_time" => $lastotptime,
      "sessions" => $sessions,
    ];
    $this->userData->set('otp_login', $uid, 'otp_user_data', $session_user_data);

    // Login programmatically as a user.
    $user = $this->entityTypeManager->getStorage('user')->load($uid);
    user_login_finalize($user);

    return $six_digit_random_sessionid;
  }

  /**
   * {@inheritdoc}
   */
  public function userTiniyoOtpLogout($session_id, $mobile_number) {
    $users = $this->entityTypeManager->getStorage('user')
      ->loadByProperties(['phone_number' => $mobile_number]);
    $user = reset($users);
    if ($user) {
      $uid = $user->id();
    }
    $data = $this->userData->get('otp_login', $uid, 'otp_user_data');
    $lastotptime = $data['last_otp_time'];
    if (($key = array_search($session_id, $data['sessions'])) !== FALSE) {
      unset($data['sessions'][$key]);
      $sessions = array_values($data['sessions']);
    }
    else {
      $sessions = $data['sessions'];
    }
    $session_user_data = [
      "mobile_number" => $mobile_number,
      "otps" => [],
      "last_otp_time" => $lastotptime,
      "sessions" => $sessions,
    ];
    $this->userData->set('otp_login', $uid, 'otp_user_data', $session_user_data);
  }

}
