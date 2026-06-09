<?php

declare(strict_types=1);

namespace Drupal\ai_test\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\ConfigTarget;
use Drupal\Core\Form\FormStateInterface;

/**
 * Test form for ai_provider_configuration with #config_target.
 */
final class AiProviderConfigurationConfigTargetForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ai_provider_configuration_config_target_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['ai_test.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = parent::buildForm($form, $form_state);

    $form['provider_config'] = [
      '#type' => 'ai_provider_configuration',
      '#title' => $this->t('AI Provider Configuration'),
      '#description' => $this->t('Select an AI provider and model.'),
      '#operation_type' => 'chat',
      '#advanced_config' => TRUE,
      '#default_provider_allowed' => TRUE,
      '#required' => FALSE,
      '#config_target' => new ConfigTarget('ai_test.settings', 'provider_config'),
    ];

    return parent::buildForm($form, $form_state);
  }

}
