<?php

namespace Drupal\xinshi_linux_do\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure linux.do OAuth client settings.
 */
class LinuxDoSettingsForm extends ConfigFormBase {

  const CONFIG_NAME = 'xinshi_linux_do.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'xinshi_linux_do_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [self::CONFIG_NAME];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(self::CONFIG_NAME);

    $form['login_activate'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable linux.do login'),
      '#default_value' => $config->get('login_activate'),
    ];

    $form['client_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client ID'),
      '#default_value' => $config->get('client_id'),
      '#required' => TRUE,
    ];

    $form['client_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client secret'),
      '#default_value' => $config->get('client_secret'),
      '#required' => TRUE,
      '#description' => $this->t('Stored in plain config; protect this site config export.'),
    ];

    $form['endpoints'] = [
      '#type' => 'details',
      '#title' => $this->t('OAuth endpoints'),
      '#open' => FALSE,
    ];
    $form['endpoints']['authorize_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Authorize endpoint'),
      '#default_value' => $config->get('authorize_url'),
      '#required' => TRUE,
    ];
    $form['endpoints']['token_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Token endpoint'),
      '#default_value' => $config->get('token_url'),
      '#required' => TRUE,
    ];
    $form['endpoints']['userinfo_url'] = [
      '#type' => 'url',
      '#title' => $this->t('User info endpoint'),
      '#default_value' => $config->get('userinfo_url'),
      '#required' => TRUE,
    ];

    $form['download_avatar'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Download avatar to local public:// on first login'),
      '#default_value' => $config->get('download_avatar'),
    ];

    $form['help'] = [
      '#type' => 'item',
      '#markup' => $this->t('Register your application at @url and set Redirect URI to <code>@uri</code>.', [
        '@url' => 'https://connect.linux.do/dash/login',
        '@uri' => $GLOBALS['base_url'] . '/user/login/linux_do/callback',
      ]),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config(self::CONFIG_NAME)
      ->set('login_activate', (bool) $form_state->getValue('login_activate'))
      ->set('client_id', trim($form_state->getValue('client_id')))
      ->set('client_secret', trim($form_state->getValue('client_secret')))
      ->set('authorize_url', trim($form_state->getValue('authorize_url')))
      ->set('token_url', trim($form_state->getValue('token_url')))
      ->set('userinfo_url', trim($form_state->getValue('userinfo_url')))
      ->set('download_avatar', (bool) $form_state->getValue('download_avatar'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
