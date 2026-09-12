<?php

namespace Drupal\fpa\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Form handler for adding fpa settings.
 */
class FpaSettingForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'fpa_setting_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'fpa.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->configFactory->getEditable('fpa.settings');

    $form['disabled_sections'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Disabled sections'),
      '#options' => [
        'help' => $this->t('Page help text'),
        'modules' => $this->t('Module listing'),
        'roles' => $this->t('Role filter'),
        'status' => $this->t('Permission status filter'),
      ],
      '#default_value' => !empty($config->get('disabled_sections')) ? $config->get('disabled_sections') : [],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->configFactory->getEditable('fpa.settings')
      ->set('disabled_sections', array_filter($form_state->getValue('disabled_sections')))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
