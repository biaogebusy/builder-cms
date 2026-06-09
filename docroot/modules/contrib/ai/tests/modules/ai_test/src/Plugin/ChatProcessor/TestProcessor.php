<?php

namespace Drupal\ai_test\Plugin\ChatProcessor;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\ChatProcessor;
use Drupal\ai\Base\ChatProcessorBase;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ChatOutput;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A ping pong test processor for testing purposes.
 */
#[ChatProcessor(
  id: 'test_processor',
  label: new TranslatableMarkup('Test Processor'),
  description: new TranslatableMarkup('A simple test processor that performs ping pong chat interactions.'),
)]
class TestProcessor extends ChatProcessorBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'pongs' => 1,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['pongs'] = [
      '#type' => 'number',
      '#title' => $this->t('Pongs'),
      '#description' => $this->t('How many pongs to my pings.'),
      '#default_value' => $this->configuration['pongs'] ?? 1,
      '#min' => 1,
      '#max' => 20,
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

    // Get the user's message from the input.
    $messages = $input->getMessages();
    // Get the last user message.
    $user_message = $messages[array_key_last($messages)];

    if (empty($user_message)) {
      throw new \InvalidArgumentException('No user message found in input.');
    }

    // Get the message text (assumes not streaming).
    $text = $user_message->getText();

    // Repeat the message pong amount of times.
    $pongs = $this->configuration['pongs'] ?? 1;
    $response_text = str_repeat($text, $pongs) . "\n";

    // Create the response message.
    $response_message = new ChatMessage('assistant', $response_text);

    // Create and return the output.
    return new ChatOutput($response_message, [], [], NULL);
  }

}
