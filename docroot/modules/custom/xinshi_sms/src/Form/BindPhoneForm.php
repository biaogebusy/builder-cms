<?php


namespace Drupal\xinshi_sms\Form;


use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\OpenDialogCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\user\Entity\User;

class BindPhoneForm extends FormBase {

  /**
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  private $user;

  /**
   * {@inheritDoc}
   */
  public function __construct() {
    $this->user = \Drupal::currentUser();
  }


  /**
   * {@inheritDoc}
   */
  public function getFormId() {
    // TODO: Implement getFormId() method.
    return 'bind_phone_form';
  }

  /**
   * {@inheritDoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // TODO: Implement buildForm() method.
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
      '#attributes' => [
        'class' => ['use-ajax-submit', 'obtain-code'],
      ],
      '#ajax' => [
        'callback' => [$this, 'obtainCodeCallback'],
      ],
    ];

    $form['verification']['send-message'] = [
      '#type' => 'html_tag',
      '#tag' => 'span',
      '#value' => '',
      '#attributes' => ['id' => 'send-message'],
    ];

    $form['bind'] = [
      '#type' => 'submit',
      '#value' => $this->t('Bind Phone'),
      '#attributes' => [
        'class' => ['use-ajax-submit', 'button--primary'],
      ],
      '#ajax' => [
        'callback' => [$this, 'bindMobilePhoneCallback'],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // TODO: Implement submitForm() method.
  }

  /**
   * {@inheritdoc}
   */
  public function obtainCodeCallback(array &$form, FormStateInterface $form_state) {
    \Drupal::messenger()->deleteAll();
    $response = new AjaxResponse();
    $mobile_number = $form_state->getValue('mobile_number');
    if (empty($mobile_number)) {
      $message = t('Please enter the phone number');
      $response->addCommand(new InvokeCommand('#mobile-message', 'html', [$message]));
    } elseif (preg_match("/^1[34578]\d{9}$/", $mobile_number) == 0) {
      $message = t('Please enter the correct phone number');
      $response->addCommand(new InvokeCommand('#mobile-message', 'html', [$message]));
    } elseif ($this->checkoutPhoneAlreadyExists($mobile_number)) {
      $message = t('The phone number is already in use by other');
      $response->addCommand(new InvokeCommand('#mobile-message', 'html', [$message]));
    } else {
      \Drupal::service('xinshi_sms.OTP')->sendVerificationCode($mobile_number, $this->user->id());
      $response->addCommand(new InvokeCommand('#mobile-message', 'html', ['']));
      $response->addCommand(new InvokeCommand('.obtain-code', 'attr', ['disabled', TRUE]));
      $response->addCommand(new InvokeCommand('.obtain-code', 'smsSend', []));
    }
    return $response;
  }

  private function checkoutPhoneAlreadyExists($mobile_number) {
    $users = \Drupal::entityTypeManager()->getStorage('user')
      ->loadByProperties(['phone_number' => $mobile_number]);
    foreach ($users as $user) {
      if ($user->id() !== $this->user->id()) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function bindMobilePhoneCallback(array &$form, FormStateInterface $form_state) {
    \Drupal::messenger()->deleteAll();
    $response = new AjaxResponse();
    $mobile_number = $form_state->getValue('mobile_number');
    $code = $form_state->getValue('code');
    if (empty($mobile_number)) {
      $message = t('Please enter the phone number');
      $response->addCommand(new InvokeCommand('#mobile-message', 'html', [$message]));
    } elseif (preg_match("/^1[34578]\d{9}$/", $mobile_number) == 0) {
      $message = t('Please enter the correct phone number');
      $response->addCommand(new InvokeCommand('#mobile-message', 'html', [$message]));
    } elseif (empty($code)) {
      $message = t('Please enter the verification code');
      $response->addCommand(new InvokeCommand('#send-message', 'html', [$message]));
    } else {
      $uid = \Drupal::currentUser()->id();
      $is_invalid_otp = \Drupal::service('xinshi_sms.OTP')->validateOtpByUser($code, $mobile_number, $uid);
      if ($is_invalid_otp) {
        $message = $this->t('Incorrect Verification Code');
        $response->addCommand(new InvokeCommand('#send-message', 'html', [$message]));
      } else {
        $user = User::load($uid);
        $user->set('phone_number', $mobile_number);
        $user->save();
        $response->addCommand(new CloseModalDialogCommand());
        $response->addCommand(new OpenDialogCommand('#bind-success-dialog',
          'Message', '<h4>' . t('Bind mobile phone number successful.') . '</h4>',
          [
            'url' => FALSE,
            'width' => 500,
          ]));
        $message = substr_replace($mobile_number, '******', 3, 6);
        $response->addCommand(new InvokeCommand('#user-bind-phone', 'html', [$message]));
      }
    }
    return $response;
  }
}
