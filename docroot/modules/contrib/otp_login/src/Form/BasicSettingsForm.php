<?php

namespace Drupal\otp_login\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form builder for the otp_login basic settings form.
 */
class BasicSettingsForm extends ConfigFormBase {

  protected $cipherMethod;
  protected $separator;
  protected $ivLength;

  /**
   * {@inheritdoc}
   */
  public function __construct() {
    $this->cipherMethod = 'AES-256-CBC';
    $this->separator = '::';
    $this->ivLength = openssl_cipher_iv_length($this->cipherMethod);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'otp_login_basic_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['otp_login.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('otp_login.settings');
    $tiniyo_authid = $config->get('tiniyo_authid');
    $tiniyo_authsecretid = $config->get('tiniyo_authsecretid');
    $secret = $config->get('encryption_secret_key');

    $decodedKey = base64_decode($secret);
    if (isset($tiniyo_authid)) {
      list($tiniyo_authid, $authid_iv) = explode($this->separator, base64_decode($tiniyo_authid), 2);
      $authid_iv = substr($authid_iv, 0, $this->ivLength);
      $decrypted_tiniyo_authid = openssl_decrypt($tiniyo_authid, $this->cipherMethod, $decodedKey, 0, $authid_iv);
    }
    if (isset($tiniyo_authsecretid)) {
      list($tiniyo_authsecretid, $authsecretid_iv) = explode($this->separator, base64_decode($tiniyo_authsecretid), 2);
      $authsecretid_iv = substr($authsecretid_iv, 0, $this->ivLength);
      $decrypted_tiniyo_authsecretid = openssl_decrypt($tiniyo_authsecretid, $this->cipherMethod, $decodedKey, 0, $authsecretid_iv);
    }

    if (isset($decrypted_tiniyo_authid)) {
      $decrypted_tiniyo_authid = $decrypted_tiniyo_authid;
    }
    else {
      $decrypted_tiniyo_authid = '';
    }

    if (isset($decrypted_tiniyo_authsecretid)) {
      $decrypted_tiniyo_authsecretid = $decrypted_tiniyo_authsecretid;
    }
    else {
      $decrypted_tiniyo_authsecretid = '';
    }

    $form['basic'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Basic settings'),
      '#collapsible' => FALSE,
    ];
    $form['basic']['activate'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Activate OTP Login'),
      '#default_value' => $config->get('activate'),
      '#description' => $this->t('Activate authentication via OTP Login.'),
    ];

    $form['otp_type'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Choose OTP platform'),
      '#collapsible' => FALSE,
      '#collapsed' => FALSE,
    ];

    $form['otp_type']['otp_platform'] = [
      '#type' => 'radios',
      '#title' => $this->t('Choose if you want to use Tiniyo API to send OTP or keep the default, SMS framework. With SMS framework you can use any supported gateway to send OTP.'),
      '#default_value' => $config->get('otp_platform'),
      '#options' => [
        'sms' => $this->t('SMS Framework'),
        'tiniyo' => $this->t('Tiniyo API'),
      ],
    ];

    $form['otp_type']['tiniyo_authid'] = [
      '#type' => 'textfield',
      '#title' => 'Tiniyo Key (AuthID)',
      '#placeholder' => 'Enter Tiniyo Key (AuthID)',
      '#default_value' => $decrypted_tiniyo_authid,
      '#states' => [
        'visible' => [
          ':input[name="otp_platform"]' => ['value' => 'tiniyo'],
        ],
      ],
    ];

    $form['otp_type']['tiniyo_authsecretid'] = [
      '#type' => 'textfield',
      '#title' => 'Tiniyo Secret (AuthSecretID)',
      '#placeholder' => 'Enter Tiniyo Secret (AuthSecretID)',
      '#default_value' => $decrypted_tiniyo_authsecretid,
      '#states' => [
        'visible' => [
          ':input[name="otp_platform"]' => ['value' => 'tiniyo'],
        ],
      ],
    ];

    $form['otp_type']['tiniyo_otp_channel'] = [
      '#type' => 'radios',
      '#title' => 'Send OTP through:',
      '#default_value' => $config->get('tiniyo_otp_channel'),
      '#options' => [
        'sms' => $this->t('SMS'),
        'call' => $this->t('Voice'),
        'all' => $this->t('Both'),
      ],
      '#states' => [
        'visible' => [
          ':input[name="otp_platform"]' => ['value' => 'tiniyo'],
        ],
      ],
    ];

    $form['otp_type']['tiniyo_otp_length'] = [
      '#type' => 'radios',
      '#title' => 'Length of otp',
      '#default_value' => $config->get('tiniyo_otp_length'),
      '#options' => [
        '4' => $this->t('4'),
        '6' => $this->t('6'),
        '8' => $this->t('8'),
      ],
      '#states' => [
        'visible' => [
          ':input[name="otp_platform"]' => ['value' => 'tiniyo'],
        ],
      ],
    ];

    $form['purge_user'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Purge users who have been blocked / not logged-in for'),
      '#collapsible' => FALSE,
      '#collapsed' => FALSE,
    ];
    $form['purge_user']['user_blocked_value'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Days'),
      '#description' => $this->t('Enter value in days.'),
      '#default_value' => $config->get('user_blocked_value'),
    ];

    $form['purge_user']['enabled_blocked_users'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $config->get('enabled_blocked_users'),
    ];

    $form['otp_expire'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Enter time after which OTP should expire.'),
      '#collapsible' => FALSE,
      '#collapsed' => FALSE,
    ];
    $form['otp_expire']['otp_expire_value'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Minutes'),
      '#description' => $this->t('Enter value in minutes.'),
      '#default_value' => $config->get('otp_expire_value'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $config = $this->config('otp_login.settings');
    $tiniyo_authid = $form_state->getValue('tiniyo_authid');
    $tiniyo_authsecretid = $form_state->getValue('tiniyo_authsecretid');

    $secret = base64_encode(md5(rand()));
    $decodedKey = base64_decode($secret);
    $authid_iv = base64_encode(openssl_random_pseudo_bytes($this->ivLength));
    $authsecretid_iv = base64_encode(openssl_random_pseudo_bytes($this->ivLength));
    $authid_iv = substr($authid_iv, 0, $this->ivLength);
    $authsecretid_iv = substr($authsecretid_iv, 0, $this->ivLength);

    // Encryption of string process starts.
    $encrypted_tiniyo_authid = openssl_encrypt($tiniyo_authid, $this->cipherMethod, $decodedKey, 0, $authid_iv);
    $encrypted_tiniyo_authsecretid = openssl_encrypt($tiniyo_authsecretid, $this->cipherMethod, $decodedKey, 0, $authsecretid_iv);

    $config->set('activate', $form_state->getValue('activate'));
    $config->set('otp_platform', $form_state->getValue('otp_platform'));
    $config->set('tiniyo_authid', base64_encode($encrypted_tiniyo_authid . $this->separator . $authid_iv));
    $config->set('tiniyo_authsecretid', base64_encode($encrypted_tiniyo_authsecretid . $this->separator . $authsecretid_iv));
    $config->set('encryption_secret_key', $secret);
    $config->set('tiniyo_otp_channel', $form_state->getValue('tiniyo_otp_channel'));
    $config->set('tiniyo_otp_length', $form_state->getValue('tiniyo_otp_length'));
    $config->set('user_blocked_value', $form_state->getValue('user_blocked_value'));
    $config->set('enabled_blocked_users', $form_state->getValue('enabled_blocked_users'));
    $config->set('otp_expire_value', $form_state->getValue('otp_expire_value'));
    $config->save();
  }

}
