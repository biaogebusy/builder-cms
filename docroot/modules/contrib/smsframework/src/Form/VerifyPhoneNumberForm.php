<?php

declare(strict_types = 1);

namespace Drupal\sms\Form;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Flood\FloodInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\sms\Provider\PhoneNumberVerificationInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form to accept a verification code.
 */
class VerifyPhoneNumberForm extends FormBase {

  /**
   * Constructs a VerifyPhoneNumberForm object.
   *
   * @param \Drupal\Core\Flood\FloodInterface $flood
   *   The flood control mechanism.
   * @param \Drupal\sms\Provider\PhoneNumberVerificationInterface $phoneNumberVerification
   *   The phone number verification service.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   Time.
   */
  public function __construct(
    protected FloodInterface $flood,
    protected PhoneNumberVerificationInterface $phoneNumberVerification,
    MessengerInterface $messenger,
    protected TimeInterface $time,
  ) {
    $this->setMessenger($messenger);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('flood'),
      $container->get('sms.phone_number.verification'),
      $container->get('messenger'),
      $container->get('datetime.time'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'sms_verify_phone_number';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['code'] = [
      '#title' => $this->t('Verification code'),
      '#description' => $this->t('Enter the code you received from a SMS message.'),
      '#type' => 'textfield',
      '#required' => TRUE,
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Verify code'),
      '#button_type' => 'primary',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $flood_window = $this->config('sms.settings')->get('flood.verify_window');
    $flood_limit = $this->config('sms.settings')->get('flood.verify_limit');

    if (!$this->flood->isAllowed('sms.verify_phone_number', $flood_limit, $flood_window)) {
      $form_state->setError($form, $this->t('There has been too many failed verification attempts. Try again later.'));
      return;
    }

    $current_time = $this->time->getRequestTime();
    $code = $form_state->getValue('code');
    $phone_verification = $this->phoneNumberVerification
      ->getPhoneVerificationByCode($code);

    if ($phone_verification && !$phone_verification->getStatus()) {
      $entity = $phone_verification->getEntity();
      $phone_number_settings = $this->phoneNumberVerification
        ->getPhoneNumberSettingsForEntity($entity);
      $lifetime = $phone_number_settings->getVerificationCodeLifetime() ?: 0;

      if ($current_time > $phone_verification->getCreatedTime() + $lifetime) {
        $form_state->setError($form['code'], $this->t('Verification code is expired.'));
      }
    }
    else {
      $form_state->setError($form['code'], $this->t('Invalid verification code.'));
    }

    $this->flood
      ->register('sms.verify_phone_number', $flood_window);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $code = $form_state->getValue('code');
    $phone_verification = $this->phoneNumberVerification
      ->getPhoneVerificationByCode($code);
    $phone_verification
      ->setStatus(TRUE)
      ->setCode('')
      ->save();
    $this->messenger()->addMessage($this->t('Phone number is now verified.'));
  }

}
