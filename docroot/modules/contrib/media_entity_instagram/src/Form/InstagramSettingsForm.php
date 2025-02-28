<?php

namespace Drupal\media_entity_instagram\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\ConfigFormBase;

/**
 * Provides a form to configure Instagram credentials.
 */
class InstagramSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'media_entity_instagram_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['media_entity_instagram.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $settings = $this->config('media_entity_instagram.settings');

    $form['credentials'] = [
      '#type' => 'details',
      '#title' => $this->t('Facebook credentials'),
      '#description' => $this->t('To use this module you need a Facebook developer account. In the your Facebook developer dashboard you have to create an App that uses the oEmbed API.'),
      '#open' => TRUE,
    ];

    $form['credentials']['facebook_app_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('App ID'),
      '#size' => 40,
      '#maxlength' => 255,
      '#default_value' => $settings->get('facebook_app_id'),
      '#description' => $this->t('The ID of your Facebook App.'),
    ];

    $form['credentials']['facebook_app_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('App secret'),
      '#size' => 40,
      '#maxlength' => 255,
      '#default_value' => $settings->get('facebook_app_secret'),
      '#description' => $this->t('The secret of your Facebook App.'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->config('media_entity_instagram.settings')
      ->set('facebook_app_id', $form_state->getValue('facebook_app_id'))
      ->set('facebook_app_secret', $form_state->getValue('facebook_app_secret'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
