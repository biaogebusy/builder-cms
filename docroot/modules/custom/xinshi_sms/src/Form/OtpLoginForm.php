<?php

namespace Drupal\xinshi_sms\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\otp_login\Form\OtpLoginForm as BaseOtpLoginForm;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\sms\Direction;
use Drupal\sms\Message\SmsMessage;

/**
 * Class OtpLoginForm.
 */
class OtpLoginForm extends BaseOtpLoginForm {

  /**
   * The otp service.
   *
   * @var \Drupal\xinshi_sms\Otp
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
      $container->get('xinshi_sms.OTP')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#attached']['library'][] = 'xinshi_sms/opt_login';
    $form['mobile'] = [
      '#type' => 'container',
      '#prefix' => '<div id="verification-wrapper">',
      '#suffix' => '</div>',
      '#attributes' => ['class' => ['inline-container']],
    ];

    $form['mobile']['mobile_number'] = [
      '#type' => 'tel',
      '#title' => $this->t('Mobile Number'),
      '#required' => TRUE,
      '#weight' => '0',
      '#attributes' => ['autocomplete' => 'off'],
      '#description' => $this->t('Please enter the phone number'),
    ];

    $form['mobile']['mobile-message'] = [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#value' => '',
      '#attributes' => ['id' => 'mobile-message'],
    ];

    $form['verification'] = [
      '#type' => 'container',
      '#prefix' => '<div id="verification-wrapper">',
      '#suffix' => '</div>',
      '#attributes' => ['class' => ['inline-container']],
    ];
    $form['verification']['code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Verification Code'),
      '#required' => TRUE,
      '#maxlength' => 8,
      '#size' => 8,
      '#attributes' => ['autocomplete' => 'off'],
    ];
    $form['verification']['send'] = [
      '#type' => 'submit',
      '#value' => $this->t('Obtain Code'),
      '#ajax' => [
        'callback' => [$this, 'otpLoginGenerateOtpCallback'],
        'wrapper' => 'verification-wrapper',
      ],
    ];

    $form['verification']['send-message'] = [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#value' => '',
      '#attributes' => ['id' => 'send-message'],
    ];

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Register/Log in'),
      '#attributes' => ['class' => ['button--primary']],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function otpLoginGenerateOtpCallback(array &$form, FormStateInterface $form_state) {
    \Drupal::messenger()->deleteAll();
    $response = new AjaxResponse();
    $mobile_number = $form_state->getValue('mobile_number');
    if (empty($mobile_number)) {
      $message = $this->t('Please enter the phone number');
      $response->addCommand(new InvokeCommand('#mobile-message', 'html', [$message]));
    } elseif (preg_match("/^1[34578]\d{9}$/", $mobile_number) == 0) {
      $message = $this->t('Please enter the correct phone number');
      $response->addCommand(new InvokeCommand('#mobile-message', 'html', [$message]));
    } else {
      $disable_register = \Drupal::config('xinshi_sms.settings')->get('disable_register');
      if ($disable_register && empty($this->OTP->otpLoginCheckUserAlreadyExists($mobile_number))) {
        $message = $this->t('This number has not been registered');
        $response->addCommand(new InvokeCommand('#mobile-message', 'html', [$message]));
      } else {
        $this->OTP->generateOtp($mobile_number);
        $response->addCommand(new InvokeCommand('#mobile-message', 'html', ['']));
        $response->addCommand(new InvokeCommand('#edit-send', 'attr', ['disabled', TRUE]));
        $response->addCommand(new InvokeCommand('#edit-send', 'smsSend', []));
      }
    }
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $mobile_number = $form_state->getValue('mobile_number');
    if (preg_match("/^1[34578]\d{9}$/", $mobile_number) == 0) {
      $message = $this->t('Please enter the correct phone number');
      $form_state->setErrorByName('mobile_number', $message);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $otp = $form_state->getValue('code');
    $mobile_number = $form_state->getValue('mobile_number');
    $is_invalid_otp = $this->OTP->validateOtp($otp, $mobile_number);
    $user = $this->OTP->otpLoginCheckUserAlreadyExists($mobile_number);
    if (empty($user)) {
      $this->messenger()->addError($this->t('This number has not been registered'));
      $form_state->setRebuild();
    } elseif ($is_invalid_otp) {
      $this->messenger()->addError($this->t('Incorrect Verification Code'));
      $form_state->setRebuild();
    } elseif ($user) {
      $session_id = $this->OTP->userOtpLogin($otp, $mobile_number);
      // Save user cookie.
      //user_cookie_save([$mobile_number => $session_id]);
      // Redirect to user profile page.
      $url = Url::fromRoute('user.page');
      $form_state->setRedirectUrl($url);
    }
  }

}
