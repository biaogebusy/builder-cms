<?php

declare(strict_types=1);

namespace Drupal\ai\Plugin\AiGuardrail;

use Drupal\ai\Attribute\AiGuardrail;
use Drupal\ai\Guardrail\AiGuardrailPluginBase;
use Drupal\ai\Guardrail\Result\GuardrailResultInterface;
use Drupal\ai\Guardrail\Result\PassResult;
use Drupal\ai\Guardrail\Result\StopResult;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\InputInterface;
use Drupal\ai\OperationType\OutputInterface;
use Drupal\ai\Utility\TokenizerInterface;
use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Blocks input that exceeds a configurable length limit.
 *
 * Supports both character-based and token-based counting, and can check
 * just the last user message or the entire conversation.
 */
#[AiGuardrail(
  id: 'input_length_limit',
  label: new TranslatableMarkup('Input Length Limit'),
  description: new TranslatableMarkup('Blocks input that exceeds a configurable character or token count limit.'),
)]
class InputLengthLimit extends AiGuardrailPluginBase implements ConfigurableInterface, PluginFormInterface, ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * Constructs an InputLengthLimit guardrail plugin.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\ai\Utility\TokenizerInterface $tokenizer
   *   The tokenizer service.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected TokenizerInterface $tokenizer,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->setConfiguration($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ai.tokenizer'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processInput(InputInterface $input): GuardrailResultInterface {
    if (!$input instanceof ChatInput) {
      return new PassResult('Input is not a chat input, skipping.', $this);
    }

    $messages = $input->getMessages();
    if (empty($messages)) {
      return new PassResult('No messages found.', $this);
    }

    $max_length = (int) ($this->configuration['max_length'] ?? 0);
    if ($max_length <= 0) {
      return new PassResult('No length limit configured, skipping check.', $this);
    }

    $use_tokens = !empty($this->configuration['use_tokens']);
    $check_all = !empty($this->configuration['check_all_messages']);

    // Build the text to measure.
    if ($check_all) {
      $parts = [];
      foreach ($messages as $message) {
        if ($message instanceof ChatMessage) {
          $parts[] = $message->getText();
        }
      }
      $text = implode("\n", $parts);
    }
    else {
      $last_message = end($messages);
      if (!$last_message instanceof ChatMessage) {
        return new PassResult('No text message found to analyze.', $this);
      }
      $text = $last_message->getText();
    }

    // Measure length.
    if ($use_tokens) {
      $this->tokenizer->setModel($this->configuration['tokenizer_model'] ?? 'gpt-4');
      $length = $this->tokenizer->countTokens($text);
      $unit = 'tokens';
    }
    else {
      $length = mb_strlen($text);
      $unit = 'characters';
    }

    if ($length > $max_length) {
      $violation_message = $this->configuration['violation_message'] ?? 'Your input has @count @unit, which exceeds the maximum of @max @unit.';
      $violation_message = str_replace(
        ['@count', '@max', '@unit'],
        [(string) $length, (string) $max_length, $unit],
        $violation_message,
      );
      return new StopResult($violation_message, $this);
    }

    return new PassResult('Input length within limits.', $this);
  }

  /**
   * {@inheritdoc}
   */
  public function processOutput(OutputInterface $output): GuardrailResultInterface {
    return new PassResult('Output processing is not applicable for this guardrail.', $this);
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration(): array {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration): void {
    $this->configuration = $configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state): array {
    $form['max_length'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum length'),
      '#description' => $this->t('The maximum allowed length. Interpreted as characters or tokens depending on the setting below.'),
      '#default_value' => $this->configuration['max_length'] ?? 5000,
      '#min' => 1,
      '#required' => TRUE,
    ];

    $form['use_tokens'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Use token-based counting'),
      '#description' => $this->t('When enabled, the limit is applied to the number of tokens instead of characters. Uses the ai.tokenizer service.'),
      '#default_value' => $this->configuration['use_tokens'] ?? FALSE,
    ];

    $form['tokenizer_model'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Tokenizer model'),
      '#description' => $this->t('The model to use for token counting (e.g. gpt-4, gpt-3.5-turbo). Only used when token-based counting is enabled.'),
      '#default_value' => $this->configuration['tokenizer_model'] ?? 'gpt-4',
      '#states' => [
        'visible' => [
          ':input[name="guardrail_settings[use_tokens]"]' => ['checked' => TRUE],
        ],
      ],
    ];

    $form['check_all_messages'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Check total conversation length'),
      '#description' => $this->t('When enabled, the limit applies to all messages combined instead of just the last user message.'),
      '#default_value' => $this->configuration['check_all_messages'] ?? FALSE,
    ];

    $form['violation_message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Violation message'),
      '#description' => $this->t('The message displayed when the limit is exceeded. Available placeholders: @count (actual length), @max (configured limit), @unit (characters or tokens).'),
      '#default_value' => $this->configuration['violation_message'] ?? 'Your input has @count @unit, which exceeds the maximum of @max @unit.',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state): void {}

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state): void {
    $this->setConfiguration($form_state->getValues());
  }

}
