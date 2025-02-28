<?php

namespace Drupal\entity_theme_engine\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'entity_theme_engine_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $config = $this->config('entity_theme_engine.settings');

    $formats = \Drupal::entityTypeManager()->getStorage('filter_format')->loadMultiple();
    $options = [];
    foreach ($formats as $key => $format) {
      $options[$key] = $format->label();
    }
    $form['text_format'] = array(
      '#type' => 'select',
      '#title' => $this->t('Text format'),
      '#description' => $this->t('Twig Template form field text format.'),
      '#default_value' => $config->get('text_format'),
      '#options' => $options,
    );
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('entity_theme_engine.settings');
    $config->set('text_format', $form_state->getValue('text_format'));
    $config->save();
    return parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'entity_theme_engine.settings',
    ];
  }

}
