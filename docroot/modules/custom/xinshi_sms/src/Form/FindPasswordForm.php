<?php

namespace Drupal\xinshi_sms\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\otp_login\Otp;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class FindPasswordForm.
 */
class FindPasswordForm extends FormBase {

  /**
   * The otp service.
   *
   * @var \Drupal\otp_login\Otp
   */
  protected $OTP;

  /**
   * @var AccountInterface
   */
  protected $account;

  /**
   * @var User
   */
  protected $user;


  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('xinshi_sms.OTP'),
      $container->get('current_user')
    );
  }

  /**
   * Creates an object.
   * FindPasswordForm constructor.
   * @param Otp $otp_login
   * @param AccountInterface $current_user
   */
  public function __construct(Otp $otp_login, AccountInterface $current_user) {
    $this->OTP = $otp_login;
    $this->account = $current_user;
    if ($this->account->isAuthenticated()) {
      $this->user = User::load($this->account->id());
    }
  }

  /**
   * {@inheritDoc}
   */
  public function getFormId() {
    // TODO: Implement getFormId() method.
    return 'user_find_password';
  }

  /**
   * {@inheritDoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // TODO: Implement buildForm() method.
    $step = $form_state->getValue('step') ?? 1;
    $wrapper_id = 'find-password-wrapper';
    $form['pass'] = [
      '#type' => 'container',
      '#prefix' => '<div id="' . $wrapper_id . '">',
      '#suffix' => '</div>',
    ];

    switch ($step) {
      case 1:
        $this->verifyAccount($form, $form_state);
        break;
      case 2:
        $this->password($form, $form_state);
        break;
    }
    return $form;
  }

  /**
   * Verify account form.
   * @param array $form
   * @param FormStateInterface $form_state
   */
  public function verifyAccount(array &$form, FormStateInterface $form_state) {
    $wrapper_id = 'find-password-wrapper';
    if ($this->account->isAnonymous()) {
      $form['pass']['mobile_number'] = [
        '#type' => 'tel',
        '#title' => $this->t('Mobile Number'),
        '#required' => TRUE,
        '#weight' => '0',
        '#attributes' => ['autocomplete' => 'off'],
        '#description' => $this->t('Please enter the phone number'),
      ];
    } else {
      if ($this->user->get('phone_number')->isEmpty()) {
        $form['message'] = [
          '#type' => 'html_tag',
          '#tag' => 'p',
          '#value' => $this->t('You have not bound a mobile phone number,Please bound the mobile phone number first!'),
        ];
        return;
      }
      $mobile_number = $this->user->get('phone_number')->value;
      $form['pass']['message'] = [
        '#type' => 'html_tag',
        '#tag' => 'p',
        '#value' => $this->t('Your verification code will be send to %number.', ['%number' => substr_replace($mobile_number, '****', 3, 4)]),
      ];
      $form['pass']['mobile_number'] = [
        '#type' => 'hidden',
        '#value' => $mobile_number,
      ];
    }

    $form['pass']['actions'] = ['#type' => 'actions'];
    $form['pass']['actions']['next'] = [
      '#type' => 'submit',
      '#value' => $this->t('Next Step'),
      '#submit' => [[$this, 'nextSubmit']],
      '#ajax' => [
        'callback' => [$this, 'nextAjax'],
        'wrapper' => $wrapper_id,
        'effect' => 'fade',
      ],
    ];
  }

  /**
   * Reset password form.
   * @param array $form
   * @param FormStateInterface $form_state
   */
  public function password(array &$form, FormStateInterface $form_state) {
    $form['mobile_number'] = [
      '#type' => 'hidden',
      '#value' => $form_state->getValue('mobile_number'),
    ];
    $form['#attached']['library'][] = 'xinshi_sms/opt_find_pass';
    $form['message'] = [
      '#type' => 'html_tag',
      '#tag' => 'p',
      '#value' => $this->t('Your verification code has been sent, if you have not received it, please click the [Obtain Code] button.'),
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
      '#attributes' => ['class' => ['js-verification-send','btn-verification-send']],
      '#ajax' => [
        'wrapper' => 'verification-wrapper',
        'callback' => [$this, 'sendAjax'],
        'effect' => 'fade',
      ],
    ];

    $form['verification']['send-message'] = [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#value' => '',
      '#attributes' => ['id' => 'send-message','class'=>['send-message']],
    ];

    $form['pass'] = [
      '#required' => TRUE,
      '#type' => 'password_confirm',
      '#size' => 25,
      '#weight' => 99,
    ];

    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Submit'),
      '#attributes' => ['class' => ['button--primary']],
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if ($mobile_number = $form_state->getValue('mobile_number')) {
      if (preg_match("/^1[34578]\d{9}$/", $mobile_number) == 0) {
        $message = $this->t('Please enter the correct phone number');
        $form_state->setErrorByName('mobile_number', $message);
      } elseif (empty($this->OTP->otpLoginCheckUserAlreadyExists($mobile_number))) {
        $message = $this->t('This number has not been registered');
        $form_state->setErrorByName('mobile_number', $message);
      }
    }
  }

  /**
   * Next submit.
   * @param array $form
   * @param FormStateInterface $form_state
   */
  public function nextSubmit(array $form, FormStateInterface $form_state) {
    $mobile_number = $form_state->getValue('mobile_number');
    $this->OTP->generateOtp($mobile_number);
    $form_state->setValue('step', 2);
    $form_state->setRebuild();
  }

  public function nextAjax(array $form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * Send code
   * @param array $form
   * @param FormStateInterface $form_state
   * @return AjaxResponse
   */
  public function sendAjax(array &$form, FormStateInterface $form_state) {
    \Drupal::messenger()->deleteAll();
    $response = new AjaxResponse();
    $mobile_number = $form_state->getValue('mobile_number');
    $this->OTP->generateOtp($mobile_number);
    $response->addCommand(new InvokeCommand('.mobile-message', 'html', ['']));
    $response->addCommand(new InvokeCommand('.js-verification-send', 'attr', ['disabled', TRUE]));
    $response->addCommand(new InvokeCommand('.js-verification-send', 'smsSend', []));
    return $response;
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // TODO: Implement submitForm() method.
    $otp = $form_state->getValue('code');
    $mobile_number = $form_state->getValue('mobile_number');
    $is_invalid_otp = $this->OTP->validateOtp($otp, $mobile_number);
    $user = $this->account->isAuthenticated() ? $this->user : $this->OTP->otpLoginCheckUserAlreadyExists($mobile_number);
    if (empty($user)) {
      $this->messenger()->addError($this->t('This number has not been registered'));
      $form_state->setValue('step', 2);
      $form_state->setRebuild();
    } elseif ($is_invalid_otp) {
      $this->messenger()->addError($this->t('Incorrect Verification Code'));
      $form_state->setValue('step', 2);
      $form_state->setRebuild();
    } elseif ($user) {
      $user->setPassword($form_state->getValue('pass'));
      $user->save();
      if (!\Drupal::request()->get('destination')) {
        $url = Url::fromRoute($this->account->isAuthenticated() ? 'user.login' : 'user.page');
        $form_state->setRedirectUrl($url);
      }
    }
  }
}
