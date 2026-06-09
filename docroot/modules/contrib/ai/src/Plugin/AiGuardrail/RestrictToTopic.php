<?php

declare(strict_types=1);

namespace Drupal\ai\Plugin\AiGuardrail;

use Drupal\ai\Attribute\AiGuardrail;
use Drupal\ai\Guardrail\AiGuardrailPluginBase;
use Drupal\ai\Guardrail\NeedsAiPluginManagerTrait;
use Drupal\ai\Guardrail\NonDeterministicGuardrailInterface;
use Drupal\ai\Guardrail\NonStreamableGuardrailInterface;
use Drupal\ai\Guardrail\Result\GuardrailResultInterface;
use Drupal\ai\Guardrail\Result\PassResult;
use Drupal\ai\Guardrail\Result\StopResult;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\InputInterface;
use Drupal\ai\OperationType\OutputInterface;
use Drupal\ai\Service\AiProviderFormHelper;
use Drupal\ai\Service\PromptJsonDecoder\PromptJsonDecoderInterface;
use Drupal\ai\Utility\CastUtility;
use Drupal\ai\Utility\Textarea;
use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the Restrict to Topic guardrail.
 */
#[AiGuardrail(
  id: 'restrict_to_topic',
  label: new TranslatableMarkup('Restrict to Topic'),
  description: new TranslatableMarkup(
    "Checks if text's main topic is specified within a list of valid topics."
  ),
)]
final class RestrictToTopic extends AiGuardrailPluginBase implements ConfigurableInterface, PluginFormInterface, ContainerFactoryPluginInterface, NonDeterministicGuardrailInterface, NonStreamableGuardrailInterface {

  use NeedsAiPluginManagerTrait;
  use StringTranslationTrait;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly AiProviderFormHelper $aiProviderFormHelper,
    private readonly PromptJsonDecoderInterface $promptJsonDecoder,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->setConfiguration($configuration);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): self {
    return new self(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('ai.form_helper'),
      $container->get('ai.prompt_json_decode'),
    );
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
  public function buildConfigurationForm(
    array $form,
    FormStateInterface $form_state,
  ): array {
    $form['valid_topics'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Valid Topics'),
      '#description' => $this->t('List of valid topics, one per line.'),
      '#default_value' => $this->configuration['valid_topics'] ?? '',
      // This property will land into core soon, see
      // https://www.drupal.org/project/drupal/issues/3202631. It can stay
      // after this is added to Drupal core.
      '#normalize_newlines' => TRUE,
      // Until that the custom value callback is needed. Should be removed
      // after the issue mentioned above is merged into core and the minimum
      // supported Drupal version includes `#normalize_newlines` property.
      '#value_callback' => [Textarea::class, 'valueCallback'],
    ];

    $form['invalid_topics'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Invalid Topics'),
      '#description' => $this->t('List of invalid topics, one per line.'),
      '#default_value' => $this->configuration['invalid_topics'] ?? '',
      // This property will land into core soon, see
      // https://www.drupal.org/project/drupal/issues/3202631. It can stay
      // after this is added to Drupal core.
      '#normalize_newlines' => TRUE,
      // Until that the custom value callback is needed. Should be removed
      // after the issue mentioned above is merged into core and the minimum
      // supported Drupal version includes `#normalize_newlines` property.
      '#value_callback' => [Textarea::class, 'valueCallback'],
    ];

    $form['invalid_topics_present_message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Message to send if invalid topics are present'),
      '#default_value' => $this->configuration['invalid_topics_present_message'] ?? 'The text contains invalid topics',
      // This property will land into core soon, see
      // https://www.drupal.org/project/drupal/issues/3202631. It can stay
      // after this is added to Drupal core.
      '#normalize_newlines' => TRUE,
      // Until that the custom value callback is needed. Should be removed
      // after the issue mentioned above is merged into core and the minimum
      // supported Drupal version includes `#normalize_newlines` property.
      '#value_callback' => [Textarea::class, 'valueCallback'],
    ];

    $form['valid_topics_missing_message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Message to send if no valid topics are found'),
      '#default_value' => $this->configuration['valid_topics_missing_message'] ?? 'The text does not contain any of the valid topics',
      // This property will land into core soon, see
      // https://www.drupal.org/project/drupal/issues/3202631. It can stay
      // after this is added to Drupal core.
      '#normalize_newlines' => TRUE,
      // Until that the custom value callback is needed. Should be removed
      // after the issue mentioned above is merged into core and the minimum
      // supported Drupal version includes `#normalize_newlines` property.
      '#value_callback' => [Textarea::class, 'valueCallback'],
    ];

    if ($form_state->getValue('llm_ai_provider') == NULL) {
      $form_state->setValue('llm_ai_provider', $this->getConfiguration()['llm_provider'] ?? $this->getConfiguration()['llm_ai_provider'] ?? NULL);
    }
    if ($form_state->getValue('llm_ai_model') == NULL) {
      $form_state->setValue('llm_ai_model', $this->getConfiguration()['llm_model'] ?? ($this->getConfiguration()['llm_ajax_prefix']['llm_ai_model'] ?? NULL));
    }

    $this->aiProviderFormHelper->generateAiProvidersForm($form, $form_state, 'chat', 'llm', AiProviderFormHelper::FORM_CONFIGURATION_FULL, 0, '', $this->t('AI Provider'), $this->t('The provider of the AI models used by this guardrail.'), TRUE);
    $llm_configs = $this->getConfiguration()['llm_config'] ?? [];
    if ($llm_configs && count($llm_configs)) {
      foreach ($llm_configs as $key => $value) {
        $form['llm_ajax_prefix']['llm_ajax_prefix_configuration_' . $key]['#default_value'] = $value;
      }
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(
    array &$form,
    FormStateInterface $form_state,
  ): void {
    $this->aiProviderFormHelper->validateAiProvidersConfig($form, $form_state, 'chat', 'llm');
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(
    array &$form,
    FormStateInterface $form_state,
  ): void {
    $values = $form_state->getValues();

    $values['llm_model'] = $values['llm_ajax_prefix']['llm_ai_model'];
    $values['llm_provider'] = $values['llm_ai_provider'];
    unset($values['llm_ajax_prefix']['llm_ai_model']);
    unset($values['llm_ai_provider']);

    $provider = $this->getAiPluginManager()->createInstance($values['llm_provider']);
    $schema = $provider->getAvailableConfiguration('chat', $values['llm_model']);

    foreach ($values['llm_ajax_prefix'] as $key => $value) {
      $real_key = str_replace('llm_ajax_prefix_configuration_', '', $key);
      $type = $schema[$real_key]['type'] ?? 'string';
      $values['llm_config'][$real_key] = CastUtility::typeCast($type, $value);
    }
    unset($values['llm_ajax_prefix']);

    $this->setConfiguration($values);
  }

  /**
   * {@inheritdoc}
   */
  public function processInput(InputInterface $input): GuardrailResultInterface {
    if (!$input instanceof ChatInput) {
      return new PassResult('Input is not a chat input, skipping topic restriction.', $this);
    }

    $messages = $input->getMessages();
    $last_message = end($messages);

    if (!$last_message instanceof ChatMessage) {
      return new PassResult('No text message found to analyze.', $this);
    }

    $text = $last_message->getText();
    $valid_topics = array_filter(
      array_map(
        'mb_strtolower',
        array_map('trim', explode("\n", $this->configuration['valid_topics'] ?? ''))
      )
    );
    $invalid_topics = array_filter(
      array_map(
        'mb_strtolower',
        array_map('trim', explode("\n", $this->configuration['invalid_topics'] ?? ''))
      )
    );
    $all_topics = array_merge($valid_topics, $invalid_topics);
    $all_topics_formatted = implode(',', $all_topics);

    $prompt = <<<PROMPT
Given a text and a list of topics, return a valid json list of which topics are present in the text. If none, just return an empty list. Don't format the output in any other way, just return the list as JSON inside a ```json code block.

Output example when not finding anything:
-------------
```json
{"topics_present": []}
```

Output example when finding something relevant:
--------------
```json
{"topics_present": ["topic_4", "topic_6"]}
```

Text:
----
"$text"

Relevant Topics you can pick from:
------
$all_topics_formatted

Result:
------
PROMPT;

    $input = new ChatInput([
      new ChatMessage('user', $prompt),
    ]);

    $provider = $this->configuration['llm_provider'] ?? '';
    $model = $this->configuration['llm_model'] ?? '';

    if (empty($provider)) {
      $default = $this->getAiPluginManager()->getDefaultProviderForOperationType('chat');
      if ($default === NULL) {
        return new StopResult('No AI provider configured for topic classification. Please configure a default chat provider in the AI module settings.', $this);
      }
      $provider = $default['provider_id'];
      $model = $default['model_id'];
    }

    $ai_provider = $this->getAiPluginManager()->createInstance($provider);

    // @phpstan-ignore-next-line
    $ai_provider->setConfiguration($this->configuration['llm_config'] ?? []);

    // @phpstan-ignore-next-line
    $response = $ai_provider
      ->chat($input, $model, ['ai'])
      ->getNormalized();
    $response_decoded = $this->promptJsonDecoder->decode($response);
    if (!is_array($response_decoded)) {
      return new StopResult('Could not decode the AI response as JSON.', $this);
    }
    $topics_present = array_map(
      'mb_strtolower',
      $response_decoded['topics_present'] ?? []
    );

    $invalid_topics_found = [];
    $valid_topics_found = [];
    foreach ($topics_present as $topic) {
      if (\in_array($topic, $valid_topics)) {
        $valid_topics_found[] = $topic;
      }
      elseif (\in_array($topic, $invalid_topics)) {
        $invalid_topics_found[] = $topic;
      }
    }

    if (\count($invalid_topics_found) > 0) {
      return new StopResult(
        $this->configuration['invalid_topics_present_message'],
        $this,
        [
          'valid_topics' => $valid_topics,
          'invalid_topics_found' => $invalid_topics_found,
        ],
      );
    }

    if (\count($valid_topics) > 0 && \count($valid_topics_found) === 0) {
      return new StopResult(
        $this->configuration['valid_topics_missing_message'],
        $this,
        [
          'valid_topics' => $valid_topics,
          'invalid_topics_found' => $invalid_topics_found,
        ],
      );
    }

    return new PassResult(
      'The text contains valid topics',
      $this,
      [
        'valid_topics' => $valid_topics,
        'invalid_topics_found' => $invalid_topics_found,
      ],
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processOutput(OutputInterface $output): GuardrailResultInterface {
    // This guardrail only processes input, not output.
    return new PassResult('Output processing is not applicable for this guardrail.', $this);
  }

}
