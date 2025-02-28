<?php

/**
 * @file
 * Contains \Drupal\wechat\Form\WechatDefaultMessageConfigForm.
 */

namespace Drupal\wechat\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Default message config form for wechat.
 */
class WechatDefaultMessageConfigForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'wechat_default_message_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['wechat.default_message'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('wechat.default_message');
    $form['default'] = array(
      '#type' => 'textarea',
      '#title' => t('Auto reply message'),
      '#description' => t('The WeChat module will send this message to user if no other Modules.'),
      '#default_value' => $config->get('default'),
      '#required' => TRUE,
    );
    $form['follow'] = array(
      '#type' => 'textarea',
      '#title' => t('Auto reply message after follow'),
      //'#description' => t(''),
      '#default_value' => $config->get('follow'),
      '#required' => TRUE,
    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * Runs cron and reloads the page.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('wechat.default_message')
      ->set('default', $form_state->getValue('default'))
      ->set('follow', $form_state->getValue('follow'))
      ->save();

    parent::submitForm($form, $form_state);

  }

}
