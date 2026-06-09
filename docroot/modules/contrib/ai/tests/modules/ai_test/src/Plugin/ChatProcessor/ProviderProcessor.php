<?php

namespace Drupal\ai_test\Plugin\ChatProcessor;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\Attribute\ChatProcessor;
use Drupal\ai\Base\ChatProcessorBase;
use Drupal\ai\OperationType\Chat\ChatOutput;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A provider test processor for testing purposes.
 */
#[ChatProcessor(
  id: 'provider_processor',
  label: new TranslatableMarkup('Provider Processor'),
  description: new TranslatableMarkup('A simple provider processor that sends the question directly to a provider.'),
)]
class ProviderProcessor extends ChatProcessorBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The AI Provider plugin manager.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected AiProviderPluginManager $aiProviderPluginManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static(
      $configuration,
      $plugin_id,
      $plugin_definition
    );
    $instance->aiProviderPluginManager = $container->get('ai.provider');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'system_prompt' => 'You are a helpful assistant.',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['system_prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('System Prompt'),
      '#description' => $this->t('The system prompt to use for the chat provider.'),
      '#default_value' => $this->configuration['system_prompt'] ?? 'You are a helpful assistant.',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function doExecute(): ChatOutput {
    $input = $this->getInput();
    if (!$input) {
      throw new \InvalidArgumentException('Input must be set before execution.');
    }

    // Get the default provider.
    $defaults = $this->aiProviderPluginManager->getSetProvider('chat');
    if (empty($defaults)) {
      throw new \RuntimeException('No default chat provider is configured.');
    }

    $input->setSystemPrompt($this->configuration['system_prompt'] ?? 'You are a helpful assistant.');

    $response = $defaults['provider_id']->chat($input, $defaults['model_id'], [
      'provider_test_processor',
    ]);

    // Create and return the output.
    return $response;
  }

}
