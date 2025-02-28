<?php

namespace Drupal\otp_login\Form;

use Drupal\otp_login\Otp;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use function GuzzleHttp\Psr7\str;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Class OtpLoginForm.
 */
class OtpLoginForm extends FormBase {

  /**
   * The otp service.
   *
   * @var \Drupal\otp_login\Otp
   */
  protected $OTP;

  protected $cipherMethod;
  protected $separator;
  protected $ivLength;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
        $container->get('otp_login.OTP')
      );
  }

  /**
   * Creates an object.
   *
   * @param Drupal\otp_login\Otp $otpLogin
   *   The otp service.
   */
  public function __construct(Otp $otpLogin) {
    $this->OTP = $otpLogin;
    $this->cipherMethod = 'AES-256-CBC';
    $this->separator = '::';
    $this->ivLength = openssl_cipher_iv_length($this->cipherMethod);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'otp_login_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['mobile_number'] = [
      '#type' => 'tel',
      '#title' => $this->t('Mobile Number'),
      '#description' => $this->t('Enter valid mobile number.'),
      '#required' => TRUE,
      '#weight' => '0',
      '#attributes' => ['class' => ['phone_intl']],
      '#attached' => [
        'library' => ['otp_login/intl-phone'],
      ],
    ];
    $form['otp'] = [
      '#type' => 'fieldset',
      '#prefix' => '<div id="otpWrapper">',
      '#suffix' => '</div>',
    ];
    $form['otp']['generate_otp'] = [
      '#type' => 'submit',
      '#value' => $this->t('Generate OTP'),
      '#weight' => '0',
      '#submit' => ['::otpLoginGenerateOtp'],
      '#ajax' => [
        'callback' => [$this, 'otpLoginGenerateOtpCallback'],
        'wrapper' => 'otpValidateWrapper',
      ],
    ];
    $form['otp']['otp_validate'] = [
      '#type' => 'fieldset',
      '#prefix' => '<div id="otpValidateWrapper">',
      '#suffix' => '</div>',
    ];
    $otp_send = $form_state->get('otp_send');
    if ($otp_send) {
      $form['otp']['otp_validate']['msg'] = [
        '#markup' => $this->t('OTP sent successfully.'),
        '#weight' => '0',
      ];
      $form['otp']['otp_validate']['otp_value'] = [
        '#type' => 'textfield',
        '#title' => $this->t('OTP'),
        '#maxlength' => 8,
        '#size' => 8,
        '#weight' => '1',
      ];
      $form['otp']['otp_validate']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Submit'),
        '#weight' => '2',
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function otpLoginGenerateOtp(array $form, FormStateInterface $form_state) {
    $otp_platform = $this->config('otp_login.settings')->get('otp_platform');
    $mobile_number = $form_state->getValue('mobile_number');
    if ($otp_platform == 'sms') {
      $this->OTP->generateOtp($mobile_number);
    }
    elseif ($otp_platform == 'tiniyo') {
      $this->OTP->generateTiniyoOtp($mobile_number);
    }
    $form_state->set('otp_send', TRUE);
    $form_state->setRebuild();
  }

  /**
   * {@inheritdoc}
   */
  public function otpLoginGenerateOtpCallback(array &$form, FormStateInterface $form_state) {
    return $form['otp']['otp_validate'];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('otp_login.settings');
    $otp_platform = $config->get('otp_platform');
    $otp = $form_state->getValue('otp_value');
    $mobile_number = $form_state->getValue('mobile_number');
    if ($otp_platform == 'sms') {
      $is_invalid_otp = $this->OTP->validateOtp($otp, $mobile_number);
      $user = $this->OTP->otpLoginCheckUserAlreadyExists($mobile_number);

      if ($is_invalid_otp) {
        $this->messenger()->addError($this->t('Incorrect OTP. Please provide correct OTP.'));
        $form_state->setRebuild();
      }
      elseif ($user) {
        $session_id = $this->OTP->userOtpLogin($otp, $mobile_number);
        // Save user cookie.
        user_cookie_save([$mobile_number => $session_id]);
        // Redirect to user profile page.
        $url = Url::fromRoute('user.page');
        $form_state->setRedirectUrl($url);
      }
    }
    elseif ($otp_platform == 'tiniyo') {
      $base_uri = 'https://api.tiniyo.com/v1/Account/';
      $client = \Drupal::httpClient();
      $tiniyo_auth_id = $config->get('tiniyo_authid');
      $tiniyo_auth_secret_id = $config->get('tiniyo_authsecretid');

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
          'dst' => $mobile_number,
          'code' => $otp,
        ],
      ];

      $url = $base_uri . $decrypted_tiniyo_authid . '/VerificationsCheck';
      try {
        $request = str($client->post($url, $settings));
        $session_id = $this->OTP->userTiniyoOtpLogin($mobile_number);
        // Save user cookie.
        user_cookie_save([$mobile_number => $session_id]);
        // Redirect to user profile page.
        $url = Url::fromRoute('user.page');
        $form_state->setRedirectUrl($url);
      }
      catch (GuzzleException $error) {
        $response = $error->getResponse();
        $response_info = json_decode($response->getBody()->getContents());
        $this->messenger()->addError($this->t('Error: %message', ['%message' => $response_info->message]));
      }
    }
  }

}
