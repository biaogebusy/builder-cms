<?php

namespace Drupal\ai_logging\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure AI Logging settings.
 */
class AiLogFormSettings extends ConfigFormBase {

  /**
   * Config settings.
   */
  const CONFIG_NAME = 'ai_logging.settings';

  /**
   * The AI provider manager.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected $aiProviderManager;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'ai_logging_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      static::CONFIG_NAME,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(static::CONFIG_NAME);

    $form['prompt_logging'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Automatically log requests'),
      '#description' => $this->t('Log all or selective prompts and responses in the database automatically.'),
      '#default_value' => $config->get('prompt_logging'),
    ];

    $form['prompt_logging_output'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Automatically log responses'),
      '#description' => $this->t('Also log the output of the AI requests.'),
      '#default_value' => $config->get('prompt_logging_output'),
      '#states' => [
        'visible' => [
          ':input[name="prompt_logging"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['prompt_logging_tags'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Restrict automated logging by request tags'),
      '#description' => $this->t('All requests are tagged with the operation type (call, moderate, etc): to automatically log only specific operations, enter them here. Leave empty to automatically log all operation types.'),
      '#default_value' => $config->get('prompt_logging_tags'),
      '#states' => [
        'visible' => [
          ':input[name="prompt_logging"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['prompt_logging_excluded_tags'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Exclude automated logging by request tags'),
      '#description' => $this->t('Comma-separated list of tags to exclude from logging. If a request has any of these tags, it will not be logged, regardless of the included tags setting above.'),
      '#default_value' => $config->get('prompt_logging_excluded_tags'),
      '#states' => [
        'visible' => [
          ':input[name="prompt_logging"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['prompt_logging_max_messages'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum number messages to keep stored in the log'),
      '#description' => $this->t('The maximum number of messages to log in the database. Empty or 0 means unlimited. Beyond this number, older logs will be automatically deleted.'),
      '#default_value' => $config->get('prompt_logging_max_messages'),
    ];

    $form['prompt_logging_max_age'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum age of messages to keep stored in the log'),
      '#description' => $this->t('The maximum age of messages to log in the database in days. Empty or 0 means unlimited. Beyond this age, older logs will be automatically deleted.'),
      '#default_value' => $config->get('prompt_logging_max_age'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Retrieve the configuration.
    $this->config(static::CONFIG_NAME)
      ->set('prompt_logging', $form_state->getValue('prompt_logging'))
      ->set('prompt_logging_tags', $form_state->getValue('prompt_logging_tags'))
      ->set('prompt_logging_excluded_tags', $form_state->getValue('prompt_logging_excluded_tags'))
      ->set('prompt_logging_output', $form_state->getValue('prompt_logging_output'))
      ->set('prompt_logging_bundles', $form_state->getValue('prompt_logging_bundles'))
      ->set('prompt_logging_max_messages', $form_state->getValue('prompt_logging_max_messages'))
      ->set('prompt_logging_max_age', $form_state->getValue('prompt_logging_max_age'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
