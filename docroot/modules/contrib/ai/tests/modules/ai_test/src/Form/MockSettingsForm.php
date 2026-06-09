<?php

declare(strict_types=1);

namespace Drupal\ai_test\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure AI Test integration settings for this site.
 */
final class MockSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ai_test_mock_settings';
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
    $config = $this->config('ai_test.settings');

    $form['catch_results'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Catch results'),
      '#default_value' => $config->get('catch_results'),
      '#description' => $this->t('Whether to catch results from AI mock providers, so they can be used for testing.'),
    ];

    $form['catch_processing_time'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Catch processing time'),
      '#default_value' => $config->get('catch_processing_time'),
      '#description' => $this->t('Whether to catch processing time from AI mock providers, so they can be used for testing.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('ai_test.settings')
      ->set('catch_results', $form_state->getValue('catch_results'))
      ->set('catch_processing_time', $form_state->getValue('catch_processing_time'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
