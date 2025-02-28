<?php

declare(strict_types=1);

namespace Drupal\forms_steps\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a settings form for a Steps collection.
 *
 * @package Drupal\forms_steps\Form
 */
class FormsStepsSettingsForm extends ConfigFormBase {

  /**
   * Forms steps settings variable name.
   *
   * @var string
   */
  protected string $config = 'forms_steps.settings';

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [
      'forms_steps.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'forms_steps_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config($this->config);

    $form['message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Message'),
      '#description' => $this->t('Message description'),
      '#defaut_value' => $config->get('message'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config($this->config)
      ->set('message', $form_state->getValue('message'))
      ->save();
  }

}
