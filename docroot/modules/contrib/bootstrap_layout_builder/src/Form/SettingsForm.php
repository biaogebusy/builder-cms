<?php

namespace Drupal\bootstrap_layout_builder\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Bootstrap Layout Builder settings.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'bootstrap_layout_builder.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'bootstrap_layout_builder_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      static::SETTINGS,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(static::SETTINGS);

    $form['hide_section_settings'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Hide "Advanced Settings"'),
      '#default_value' => $config->get('hide_section_settings'),
    ];

    $form['live_preview'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable live preview'),
      '#default_value' => $config->get('live_preview'),
    ];

    $form['one_col_layout_class'] = [
      '#type' => 'textfield',
      '#title' => $this->t('One col layout class'),
      '#maxlength' => 20,
      '#default_value' => $config->get('one_col_layout_class') ?: 'col-12',
      '#description' => $this->t('eg: col-12.'),
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->configFactory->getEditable(static::SETTINGS)
      ->set('hide_section_settings', $form_state->getValue('hide_section_settings'))
      ->set('live_preview', $form_state->getValue('live_preview'))
      ->set('one_col_layout_class', $form_state->getValue('one_col_layout_class'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
