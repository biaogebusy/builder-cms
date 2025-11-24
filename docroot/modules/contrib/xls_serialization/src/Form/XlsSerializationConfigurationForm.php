<?php

namespace Drupal\xls_serialization\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure Xls Serialization for this site.
 */
class XlsSerializationConfigurationForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'xls_serialization_configuration_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'xls_serialization.configuration',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('xls_serialization.configuration');

    $form['xls_serialization_autosize'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Disable AutoSize of columns'),
      '#description' => $this->t("Checking this box, the width of the column won't have a flexible width but a fixed one."),
      '#default_value' => $config->get('xls_serialization_autosize'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $this->config('xls_serialization.configuration')
      ->set('xls_serialization_autosize', (bool) $form_state->getValue('xls_serialization_autosize'))
      ->save();

  }

}
