<?php

namespace Drupal\xinshi_sms\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\sms\Direction;
use Drupal\sms\Message\SmsMessage;

/**
 * Provides a form for SMS settings.
 */
class AlibabaSMSSettingsForm extends ConfigFormBase {

  /**
   * @var string
   */
  private $configName = 'xinshi_sms.alibaba_sms_settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'xinshi_sms_alibaba_sms_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $settings = $this->config($this->configName);

    $form['sign_name'] = [
      '#type' => 'textfield',
      '#title' => t('Sign Name'),
      '#default_value' => $settings->get('sign_name'),
      '#required' => TRUE,
    ];

    $form['template_code'] = [
      '#type' => 'textfield',
      '#title' => t('Template Code'),
      '#default_value' => $settings->get('template_code'),
      '#required' => TRUE,
    ];

    $form['access_key_id'] = [
      '#type' => 'textfield',
      '#title' => t('AccessKey ID'),
      '#default_value' => $settings->get('access_key_id'),
      '#required' => TRUE,
    ];

    $form['access_key_secret'] = [
      '#type' => 'textfield',
      '#title' => t('AccessKey Secret'),
      '#default_value' => $settings->get('access_key_secret'),
      '#required' => TRUE,
    ];

    return $form;
  }


  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config($this->configName)
      ->set('access_key_id', $form_state->getValue('access_key_id'))
      ->set('access_key_secret', $form_state->getValue('access_key_secret'))
      ->set('template_code', $form_state->getValue('template_code'))
      ->set('sign_name', $form_state->getValue('sign_name'))
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
