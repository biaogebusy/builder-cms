<?php

namespace Drupal\jsonapi_include\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * The class for settings form.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'jsonapi_include.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'jsonapi_include_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('jsonapi_include.settings');
    $form['use_include_query'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use jsonapi_include query in url'),
      '#default_value' => $config->get('use_include_query'),
      '#description' => $this->t('Example: http://example.com/jsonapi/node/article?include=field_tags&jsonapi_include=1'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('jsonapi_include.settings')
      ->set('use_include_query', $form_state->getValue('use_include_query'))
      ->save();
  }

}
