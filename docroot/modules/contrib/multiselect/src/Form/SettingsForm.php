<?php

namespace Drupal\multiselect\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form builder for the admin display defaults page.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'multiselect_admin_settings';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);

    $config = $this->config('multiselect.settings');

    $form['basic'] = [];
    $form['basic']['multiselect_widths'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Width of Select Boxes (in pixels)'),
      '#default_value' => $config->get('multiselect.widths'),
      '#size' => 3,
      '#field_suffix' => 'px',
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $config = $this->config('multiselect.settings');
    $config->set('multiselect.widths', $form_state->getValue('multiselect_widths'))->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['multiselect.settings'];
  }

}
