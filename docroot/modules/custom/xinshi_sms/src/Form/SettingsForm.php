<?php

namespace Drupal\xinshi_sms\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a form for SMS settings.
 */
class SettingsForm extends ConfigFormBase {


  /**
   * @var string
   */
  private $configName = 'xinshi_sms.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'xinshi_sms_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $settings = $this->config($this->configName);
    $form['login'] = [
      '#type' => 'details',
      '#title' => t('Basic settings'),
      '#collapsible' => FALSE,
      '#open' => TRUE,
    ];

    $form['login']['activate'] = [
      '#type' => 'checkbox',
      '#title' => t('Activate OTP Login'),
      '#default_value' => $settings->get('activate') ?? 0,
    ];

    $form['login']['disable_register'] = [
      '#type' => 'checkbox',
      '#title' => t('Disable User Registration'),
      '#default_value' => $settings->get('disable_register') ?? 0,
    ];

    $form['login']['enabled_find_password'] = [
      '#type' => 'checkbox',
      '#title' => t('Find Password By SMS'),
      '#default_value' => $settings->get('enabled_find_password') ?? 0,
    ];

    $form['login']['override_reset_pass'] = [
      '#type' => 'checkbox',
      '#title' => t('Override Reset Pass'),
      '#default_value' => $settings->get('override_reset_pass') ?? 0,
    ];
    return $form;
  }


  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config($this->configName)
      ->set('activate', $form_state->getValue('activate'))
      ->set('disable_register', $form_state->getValue('disable_register'))
      ->set('enabled_find_password', $form_state->getValue('enabled_find_password'))
      ->set('override_reset_pass', $form_state->getValue('override_reset_pass'))
      ->save();
    \Drupal::messenger()->addStatus(t('Save Successfully'));
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [$this->configName];
  }

}
