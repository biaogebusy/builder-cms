<?php

declare(strict_types=1);

namespace Drupal\dropai_provider\Form;

use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\Service\AiProviderFormHelper;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configure DropAI Provider settings.
 */
final class DropAiConfigForm extends ConfigFormBase {

  /**
   * Config settings.
   */
  const CONFIG_NAME = 'dropai_provider.settings';

  /**
   * The AI provider manager.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected $aiProviderManager;

  /**
   * The form helper.
   *
   * @var \Drupal\ai\Service\AiProviderFormHelper
   */
  protected $formHelper;

  /**
   * Constructs a new DropAiConfigForm object.
   */
  final public function __construct(AiProviderPluginManager $ai_provider_manager, AiProviderFormHelper $form_helper) {
    $this->aiProviderManager = $ai_provider_manager;
    $this->formHelper = $form_helper;
  }

  /**
   * {@inheritdoc}
   */
  final public static function create(ContainerInterface $container) {
    return new static(
      $container->get('ai.provider'),
      $container->get('ai.form_helper')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'dropai_provider_dropai_config';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['dropai_provider.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config(static::CONFIG_NAME);

    $form['api_key'] = [
      '#type' => 'key_select',
      '#title' => $this->t('DropAI API Key'),
      '#description' => $this->t('The DropAI API Key.'),
      '#default_value' => $config->get('api_key'),
    ];

    // Enable this and add loadModelsForm() to your plugin to enable
    // configuration per model.
    /*
    $provider = $this->aiProviderManager->createInstance('dropai');
    $form['models'] = $this->formHelper->getModelsTable(
    $form, $form_state, $provider
    );
     */

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Here we set the default providers per the operation for our provider.
    $this->aiProviderManager->defaultIfNone('chat', 'dropai', 'drop-ai-text-model-1');

    $this->config('dropai_provider.settings')
      ->set('api_key', $form_state->getValue('api_key'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
