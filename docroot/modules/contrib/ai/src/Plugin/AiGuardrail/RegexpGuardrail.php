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
use Drupal\ai\OperationType\Chat\ChatOutput;
use Drupal\ai\OperationType\Chat\StreamedChatMessageIteratorInterface;
use Drupal\ai\OperationType\InputInterface;
use Drupal\ai\OperationType\OutputInterface;
use Drupal\ai\Utility\Textarea;
use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin implementation of the Regexp guardrail.
 */
#[AiGuardrail(
  id: 'regexp_guardrail',
  label: new TranslatableMarkup('Regexp Guardrail'),
  description: new TranslatableMarkup(
    "Checks if text's content matches a specified regular expression pattern."
  ),
)]
class RegexpGuardrail extends AiGuardrailPluginBase implements ConfigurableInterface, PluginFormInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function processInput(
    InputInterface $input,
  ): GuardrailResultInterface {
    if (!$input instanceof ChatInput) {
      return new PassResult('Input is not a chat input, skipping topic restriction.', $this);
    }

    $messages = $input->getMessages();
    $last_message = end($messages);

    if (!$last_message instanceof ChatMessage) {
      return new PassResult('No text message found to analyze.', $this);
    }

    $text = $last_message->getText();
    $regexp_pattern = $this->configuration['regexp_pattern'] ?? '';
    if (empty($regexp_pattern)) {
      return new PassResult('No regexp pattern configured, skipping check.', $this);
    }
    if (preg_match($regexp_pattern, $text)) {
      $violation_message = $this->configuration['violation_message'] ?? 'The text contains invalid content matching the pattern: @pattern';
      $violation_message = str_replace('@pattern', $regexp_pattern, $violation_message);

      return new StopResult($violation_message, $this);
    }

    return new PassResult('Input text passed the regexp guardrail check.', $this);
  }

  /**
   * {@inheritdoc}
   */
  public function processOutput(
    OutputInterface $output,
  ): GuardrailResultInterface {
    if (!$output instanceof ChatOutput) {
      return new PassResult('Output is not a chat output, skipping regexp check.', $this);
    }

    $normalized = $output->getNormalized();

    if ($normalized instanceof StreamedChatMessageIteratorInterface) {
      return new PassResult('Streamed output cannot be scanned by regexp guardrail.', $this);
    }

    if (!$normalized instanceof ChatMessage) {
      return new PassResult('No text message found in output to analyze.', $this);
    }

    $regexp_pattern = $this->configuration['regexp_pattern'] ?? '';
    if (empty($regexp_pattern)) {
      return new PassResult('No regexp pattern configured, skipping check.', $this);
    }

    // Scan both the assistant text and any tool call argument values the
    // model produced. Tool calls are LLM-authored content too, so PII or
    // other disallowed strings can leak through tool arguments just as
    // easily as through the message body. Argument values are walked
    // directly instead of serialized to JSON to avoid escape artifacts
    // (control characters, quotes, backslashes) that would hide matches.
    $haystacks = [$normalized->getText()];
    foreach ($normalized->getTools() ?? [] as $tool) {
      foreach ($tool->getArguments() as $argument) {
        $this->collectScalarStrings($argument->getValue(), $haystacks);
      }
    }

    foreach ($haystacks as $haystack) {
      if ($haystack !== '' && preg_match($regexp_pattern, $haystack)) {
        $violation_message = $this->configuration['violation_message'] ?? 'The text contains invalid content matching the pattern: @pattern';
        $violation_message = str_replace('@pattern', $regexp_pattern, $violation_message);

        return new StopResult($violation_message, $this);
      }
    }

    return new PassResult('Output text passed the regexp guardrail check.', $this);
  }

  /**
   * Recursively collects scalar string values from a mixed input.
   *
   * Used to flatten tool call argument values into a list of raw strings so
   * the configured regex can match against the original characters (including
   * newlines, tabs and quotes) without JSON escape artifacts.
   *
   * @param mixed $value
   *   The value to walk. Strings and other scalars are appended, arrays and
   *   iterables are recursed into, objects with __toString are cast.
   * @param array $haystacks
   *   The list to append flattened string values to. Passed by reference.
   */
  private function collectScalarStrings(mixed $value, array &$haystacks): void {
    if (is_string($value)) {
      $haystacks[] = $value;
      return;
    }
    if (is_bool($value) || is_int($value) || is_float($value)) {
      $haystacks[] = (string) $value;
      return;
    }
    if (is_array($value) || $value instanceof \Traversable) {
      foreach ($value as $item) {
        $this->collectScalarStrings($item, $haystacks);
      }
      return;
    }
    if (is_object($value) && method_exists($value, '__toString')) {
      $haystacks[] = (string) $value;
    }
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
  public function buildConfigurationForm(
    array $form,
    FormStateInterface $form_state,
  ): array {
    $form['regexp_pattern'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => $this->t('Regexp Pattern'),
      '#description' => $this->t('Enter a regular expression pattern, including delimiters. Example: @example', [
        '@example' => '/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/i',
      ]),
      '#default_value' => $this->configuration['regexp_pattern'] ?? '',
      // This property will land into core soon, see
      // https://www.drupal.org/project/drupal/issues/3202631. It can stay
      // after this is added to Drupal core.
      '#normalize_newlines' => TRUE,
      // Until that the custom value callback is needed. Should be removed
      // after the issue mentioned above is merged into core and the minimum
      // supported Drupal version includes `#normalize_newlines` property.
      '#value_callback' => [Textarea::class, 'valueCallback'],
    ];

    $form['violation_message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Violation Message'),
      '#default_value' => $this->configuration['violation_message'] ?? 'The text contains invalid content matching the pattern: @pattern',
      '#description' => $this->t('You can use the placeholder %placeholder to include the pattern used.', [
        '%placeholder' => '@pattern',
      ]),
      // This property will land into core soon, see
      // https://www.drupal.org/project/drupal/issues/3202631. It can stay
      // after this is added to Drupal core.
      '#normalize_newlines' => TRUE,
      // Until that the custom value callback is needed. Should be removed
      // after the issue mentioned above is merged into core and the minimum
      // supported Drupal version includes `#normalize_newlines` property.
      '#value_callback' => [Textarea::class, 'valueCallback'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(
    array &$form,
    FormStateInterface $form_state,
  ): void {
    $pattern = $form_state->getValue('regexp_pattern');
    if (!empty($pattern)) {
      // Use a recording error handler to capture the actual PCRE compiler
      // diagnostic from the E_WARNING text (e.g. "Compilation failed: missing
      // terminating ] at offset 5"). The @ operator and error_get_last() cannot
      // be used here because @ suppresses the error before it is stored.
      // preg_last_error_msg() only returns generic codes like "Internal error".
      $error_message = NULL;
      set_error_handler(
        static function () use (&$error_message): bool {
          $errstr = (string) func_get_arg(1);
          $error_message = preg_replace('/^preg_match\(\): /', '', $errstr);
          return TRUE;
        },
        E_WARNING
      );
      $result = preg_match($pattern, '');
      restore_error_handler();

      if ($result === FALSE) {
        $form_state->setErrorByName(
          'regexp_pattern',
          $this->t('Invalid regular expression: @error', [
            '@error' => $error_message ?? preg_last_error_msg(),
          ])
        );
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(
    array &$form,
    FormStateInterface $form_state,
  ): void {
    $this->setConfiguration($form_state->getValues());
  }

}
