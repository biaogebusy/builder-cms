<?php

namespace Drupal\ai_automators\PluginBaseClasses;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\Dto\HostnameFilterDto;
use Drupal\ai\Enum\AiModelCapability;
use Drupal\ai\Guardrail\AiGuardrailHelper;
use Drupal\ai\Guardrail\Result\StopResult;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\GenericType\ImageFile;
use Drupal\ai\OperationType\InputInterface;
use Drupal\ai\Service\AiProviderFormHelper;
use Drupal\ai\Service\PromptJsonDecoder\PromptJsonDecoderInterface;
use Drupal\ai\Utility\CastUtility;
use Drupal\ai_automators\Exceptions\AiAutomatorResponseErrorException;
use Drupal\ai_automators\Exceptions\AiAutomatorTypeNotRunnable;
use Drupal\ai_automators\PluginInterfaces\AiAutomatorPostCheckIfEmptyInterface;
use Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface;
use Drupal\ai_automators\Traits\GeneralHelperTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * This is a base class for all LLM rule helpers.
 */
abstract class RuleBase implements AiAutomatorTypeInterface, AiAutomatorPostCheckIfEmptyInterface, ContainerFactoryPluginInterface {

  use GeneralHelperTrait;
  use StringTranslationTrait;

  /**
   * The LLM type.
   *
   * @var string
   */
  protected string $llmType = 'chat';

  /**
   * The json schema.
   *
   * @var array
   */
  public array $jsonSchema = [];

  /**
   * The plugin manager.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected AiProviderPluginManager $aiPluginManager;

  /**
   * The form helper.
   *
   * @var \Drupal\ai\Service\AiProviderFormHelper
   */
  protected AiProviderFormHelper $formHelper;

  /**
   * The prompt JSON decoder.
   *
   * @var \Drupal\ai\Service\PromptJsonDecoder\PromptJsonDecoderInterface
   */
  protected PromptJsonDecoderInterface $promptJsonDecoder;

  /**
   * The AI guardrail helper.
   *
   * @var \Drupal\ai\Guardrail\AiGuardrailHelper
   */
  protected AiGuardrailHelper $aiGuardrailHelper;

  /**
   * The logger channel.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface|null
   */
  protected ?LoggerChannelInterface $logger = NULL;

  /**
   * Constructs a new AiClientBase abstract class.
   *
   * @param \Drupal\ai\AiProviderPluginManager $pluginManager
   *   The plugin manager.
   * @param \Drupal\ai\Service\AiProviderFormHelper $formHelper
   *   The form helper.
   * @param \Drupal\ai\Service\PromptJsonDecoder\PromptJsonDecoderInterface $promptJsonDecoder
   *   The prompt JSON decoder.
   * @param \Drupal\ai\Guardrail\AiGuardrailHelper $aiGuardrailHelper
   *   The AI guardrail helper.
   * @param \Drupal\Core\Logger\LoggerChannelInterface|null $logger
   *   The logger channel for ai_automators.
   */
  public function __construct(
    AiProviderPluginManager $pluginManager,
    AiProviderFormHelper $formHelper,
    PromptJsonDecoderInterface $promptJsonDecoder,
    AiGuardrailHelper $aiGuardrailHelper,
    ?LoggerChannelInterface $logger = NULL,
  ) {
    $this->aiPluginManager = $pluginManager;
    $this->formHelper = $formHelper;
    $this->promptJsonDecoder = $promptJsonDecoder;
    $this->aiGuardrailHelper = $aiGuardrailHelper;
    $this->logger = $logger;
  }

  /**
   * Load from dependency injection container.
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('ai.provider'),
      $container->get('ai.form_helper'),
      $container->get('ai.prompt_json_decode'),
      $container->get('ai.guardrail_helper'),
      $container->get('logger.factory')->get('ai_automators'),
    );
  }

  /**
   * Apply a configured guardrail set to an input prior to dispatch.
   *
   * @param \Drupal\ai\OperationType\InputInterface $input
   *   The input about to be sent to the provider.
   * @param array $automatorConfig
   *   The automator configuration; if it carries a `guardrail_set_id`, that set
   *   is attached to the input.
   *
   * @return \Drupal\ai\OperationType\InputInterface
   *   The (possibly cloned) input with the guardrail set attached, or the
   *   original input if no guardrail set is configured / available.
   */
  protected function applyGuardrailsToInput(InputInterface $input, array $automatorConfig): InputInterface {
    $setId = $automatorConfig['guardrail_set_id'] ?? NULL;
    if (!$setId) {
      return $input;
    }
    return $this->aiGuardrailHelper->applyGuardrailSetToChatInput($setId, $input);
  }

  /**
   * Abort the Automator run if the guardrail set's stop threshold was reached.
   *
   * Mirrors the aggregation in core's GuardrailsEventSubscriber: scores from
   * StopResult outcomes across every attached set and mode are summed and
   * compared against the lowest stop threshold of the attached sets. If the
   * total reaches or exceeds that threshold, the run is aborted.
   *
   * @param \Drupal\ai\OperationType\InputInterface $input
   *   The input that was just sent to the provider.
   *
   * @throws \Drupal\ai_automators\Exceptions\AiAutomatorResponseErrorException
   *   If the input was blocked by the configured guardrail set.
   */
  protected function assertNotStoppedByGuardrail(InputInterface $input): void {
    $sets = $input->getGuardrailSets();
    if (!$sets) {
      return;
    }
    $score = 0.0;
    $messages = [];
    foreach ($input->getAllGuardrailResults() as $modeResults) {
      foreach ($modeResults as $result) {
        if ($result instanceof StopResult) {
          $score += $result->getScore();
          $messages[] = $result->getMessage();
        }
      }
    }
    // Use the lowest threshold across attached sets so that any set crossing
    // its threshold blocks the run. AI Automator currently attaches a single
    // set per call, so this matches the prior single-set behavior.
    $threshold = PHP_FLOAT_MAX;
    foreach ($sets as $set) {
      $threshold = min($threshold, $set->getStopThreshold());
    }
    if (!$messages || $score < $threshold) {
      return;
    }
    $combined = implode(' ', array_filter($messages));
    if ($this->logger) {
      $this->logger->warning('AI Automator run blocked by guardrail set @set (score @score >= threshold @threshold): @msg', [
        '@set' => implode(', ', array_keys($sets)),
        '@score' => $score,
        '@threshold' => $threshold,
        '@msg' => $combined,
      ]);
    }
    throw new AiAutomatorResponseErrorException('Blocked by guardrail: ' . $combined);
  }

  /**
   * {@inheritDoc}
   */
  public function needsPrompt() {
    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function advancedMode() {
    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function checkIfEmpty(array $value, array $automatorConfig = []) {
    return $value;
  }

  /**
   * Optional post-check hook for complex empty-state normalization.
   *
   * Rules can override this to adjust values after checkIfEmpty().
   *
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity being worked on.
   * @param array $value
   *   The value response.
   * @param array $automatorConfig
   *   The automator config.
   *
   * @return array
   *   Returns an empty array if the value should be considered empty.
   */
  public function postCheckIfEmpty(ContentEntityInterface $entity, array $value, array $automatorConfig = []): array {
    return $value;
  }

  /**
   * {@inheritDoc}
   */
  public function placeholderText() {
    return 'Enter a prompt here.';
  }

  /**
   * {@inheritDoc}
   */
  public function ruleIsAllowed(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition) {
    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function helpText() {
    return "";
  }

  /**
   * {@inheritDoc}
   */
  public function allowedInputs() {
    return [
      'text_long',
      'text',
      'string',
      'string_long',
      'text_with_summary',
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function tokens(ContentEntityInterface $entity) {
    return [
      'context' => 'The cleaned text from the base field.',
      'raw_context' => 'The raw text from the base field. Can include HTML',
      'max_amount' => 'The max amount of entries to set. If unlimited this value will be empty.',
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function extraFormFields(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, FormStateInterface $formState, array $defaultValues = []) {
    return [];
  }

  /**
   * {@inheritDoc}
   */
  public function extraAdvancedFormFields(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, FormStateInterface $formState, array $defaultValues = []) {
    $form = [];
    $guardrailOptions = [];
    foreach ($this->aiGuardrailHelper->getRepository()->getAllGuardrailSets() as $guardrailSet) {
      $guardrailOptions[$guardrailSet->id()] = $guardrailSet->label();
    }
    $aiConfig = $formState->get('ai_automator');
    $form['automator_guardrail_set_id'] = [
      '#type' => 'select',
      '#title' => $this->t('Guardrail set'),
      '#description' => $this->t('If set, the Automator input and output are evaluated against this Guardrail Set. Runs that trigger a stop verdict are aborted and the field is not updated.'),
      '#options' => $guardrailOptions,
      '#empty_option' => $this->t('- None -'),
      '#empty_value' => '',
      '#default_value' => $aiConfig ? ($aiConfig->get('guardrail_set_id') ?? '') : '',
      '#weight' => 30,
    ];
    // Load the AI models.
    $providers = $this->formHelper->getAiProvidersOptions($this->llmType);

    // If no providers are available, show an inline message and return early.
    if (empty($providers)) {
      $operation_type = $this->aiPluginManager->getOperationType($this->llmType, TRUE);
      $operation_label = $operation_type['label'] ?? $this->llmType;
      $form['no_providers_message'] = [
        '#markup' => $this->t('No AI providers are configured for %type automator. Please <a href=":url" target="_blank">configure a provider</a> to use this feature.', [
          '%type' => $operation_label,
          ':url' => 'https://project.pages.drupalcode.org/ai/latest/providers/matris/',
        ]),
        '#prefix' => '<div class="messages messages--warning">',
        '#suffix' => '</div>',
      ];
      return $form;
    }

    // Add to the start of the array.
    if ($this->llmType == 'chat') {
      $providers = [
        'default_json' => $this->t('Default Advanced JSON model'),
        'default_structured_response' => $this->t('Default Structured Response model'),
        'default_vision' => $this->t('Default Vision model'),
      ] + $providers;
    }
    else {
      $defaultOperationType = $this->aiPluginManager->getOperationType($this->llmType, TRUE);
      if ($defaultOperationType) {
        $providers = [
          'default' => $this->t('Default %llm_type model', [
            '%llm_type' => $defaultOperationType['label'],
          ]),
        ] + $providers;
      }
    }
    $defaults = $this->aiPluginManager->getDefaultProviderForOperationType($this->llmType);
    $provider = $formState->getValue('automator_ai_provider');
    if (!$provider) {
      $provider = $defaultValues['automator_ai_provider'] ?? NULL;
      if (empty($provider)) {
        $provider = key($providers);
      }
      if (empty($provider) && !empty($defaults['provider_id'])) {
        $provider = $defaults['provider_id'];
      }
    }
    $form['automator_ai_provider'] = [
      '#type' => 'select',
      '#title' => $this->t('AI Provider'),
      '#options' => $providers,
      '#default_value' => $provider,
      '#ajax' => [
        'callback' => '\Drupal\ai_automators\PluginBaseClasses\RuleBase::loadModelsAjaxCallback',
        'wrapper' => 'provider_ajax_wrapper',
      ],
    ];
    $form['ajax_prefix'] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Provider Configuration'),
      '#attributes' => [
        'id' => 'provider_ajax_wrapper',
      ],
      '#states' => [
        'visible' => [
          ':input[name="automator_ai_provider"]' => ['!value' => ''],
        ],
      ],
    ];

    $llmInstance = NULL;
    $model = NULL;
    if ($provider && !in_array($provider, [
      'default_structured_response',
      'default_json',
      'default_vision',
      'default',
    ])) {
      $llmInstance = $this->aiPluginManager->createInstance($provider);
      $model = $formState->getValue('automator_ai_model');
      $models = $llmInstance->getConfiguredModels($this->llmType);
      if (!$model || !in_array($model, array_keys($models))) {
        $model = $defaultValues['automator_ai_model'] ?? NULL;
        if (isset($defaults['model_id']) && !$model) {
          $model = $defaults['model_id'];
        }
        if (empty($model) || !in_array($model, array_keys($models))) {
          $model = key($models);
        }
      }

      $form['ajax_prefix']['automator_ai_model'] = [
        '#type' => 'select',
        '#title' => $this->t('Model'),
        // Only get chat models.
        '#options' => $models,
        '#default_value' => $model,
        '#ajax' => [
          'callback' => '\Drupal\ai_automators\PluginBaseClasses\RuleBase::loadModelsAjaxCallback',
          'wrapper' => 'provider_ajax_wrapper',
        ],
      ];

      if ($model) {
        $configuration = $llmInstance->getAvailableConfiguration($this->llmType, $model);

        if (count($configuration)) {
          $form['ajax_prefix']['ai_settings'] = [
            '#type' => 'fieldset',
            '#title' => $this->t('Settings'),
          ];
          foreach ($configuration as $key => $definition) {
            $set_key = 'automator_configuration_' . $key;
            $form['ajax_prefix']['ai_settings'][$set_key]['#type'] = $this->formHelper->mapSchemaTypeToFormType($definition);
            $form['ajax_prefix']['ai_settings'][$set_key]['#required'] = $definition['required'] ?? FALSE;
            $form['ajax_prefix']['ai_settings'][$set_key]['#title'] = $definition['label'] ?? $key;
            $form['ajax_prefix']['ai_settings'][$set_key]['#description'] = $definition['description'] ?? '';
            $form['ajax_prefix']['ai_settings'][$set_key]['#default_value'] = $defaultValues[$set_key] ?? $definition['default'] ?? NULL;
            if (isset($definition['constraints'])) {
              foreach ($definition['constraints'] as $form_key => $value) {
                if ($form_key == 'options') {
                  $form['ajax_prefix']['ai_settings'][$set_key]['#options'] = array_combine($value, $value);
                  continue;
                }
                $form['ajax_prefix']['ai_settings'][$set_key]['#' . $form_key] = $value;
              }
            }
          }
        }
      }
    }

    // Add vision if it is available or default vision.
    if (($llmInstance && in_array($model, array_keys($llmInstance->getConfiguredModels('chat', [AiModelCapability::ChatWithImageVision])))) || $provider == 'default_vision') {
      // Add the image field to use.
      $form['ajax_prefix']['automator_configuration_image_field'] = [
        '#type' => 'select',
        '#title' => $this->t('Image Field'),
        '#options' => $this->getGeneralHelper()->getImageMediaFields($entity),
        '#description' => $this->t('Since this is a vision model you can choose to add an image field to the prompt.'),
        '#empty_option' => $this->t('No images'),
        '#default_value' => $defaultValues['automator_configuration_image_field'] ?? NULL,
      ];

      // Also add the possibility to add an image style.
      $form['ajax_prefix']['automator_configuration_image_style'] = [
        '#type' => 'select',
        '#title' => $this->t('Image Style'),
        '#description' => $this->t('Use an optional image style to lower costs and increase speed.'),
        '#empty_option' => $this->t('Use original'),
        '#options' => $this->getGeneralHelper()->getImageStyles(FALSE),
        '#default_value' => $defaultValues['automator_configuration_image_style'] ?? NULL,
        '#states' => [
          'visible' => [
            ':input[name="automator_configuration_image_field"]' => ['!value' => ''],
          ],
        ],
      ];
    }
    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function validateConfigValues($form, FormStateInterface $formState) {

  }

  /**
   * {@inheritDoc}
   */
  public function generateTokens(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $automatorConfig, $delta = 0) {
    $values = $entity->get($automatorConfig['base_field'])->getValue();
    return [
      'context' => strip_tags($values[$delta]['value'] ?? ''),
      'raw_context' => $values[$delta]['value'] ?? '',
      'max_amount' => $fieldDefinition->getFieldStorageDefinition()->getCardinality() == -1 ? '' : $fieldDefinition->getFieldStorageDefinition()->getCardinality(),
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function generate(ContentEntityInterface $entity, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    // Generate the real prompt if needed.
    $prompts = [];
    // @phpstan-ignore-next-line
    if (!empty($automatorConfig['mode']) && $automatorConfig['mode'] == 'token' && \Drupal::service('module_handler')->moduleExists('token')) {
      $prompts[] = \Drupal::service('ai_automator.prompt_helper')->renderTokenPrompt($automatorConfig['token'], $entity); /* @phpstan-ignore-line */
    }
    elseif ($this->needsPrompt()) {
      foreach ($entity->get($automatorConfig['base_field'])->getValue() as $i => $item) {
        // Get tokens.
        $tokens = $this->generateTokens($entity, $fieldDefinition, $automatorConfig, $i);
        $prompts[] = \Drupal::service('ai_automator.prompt_helper')->renderPrompt($automatorConfig['prompt'], $tokens, $i); /* @phpstan-ignore-line */
      }
    }
    return $prompts;
  }

  /**
   * {@inheritDoc}
   */
  public function verifyValue(ContentEntityInterface $entity, $value, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    return TRUE;
  }

  /**
   * {@inheritDoc}
   */
  public function storeValues(ContentEntityInterface $entity, array $values, FieldDefinitionInterface $fieldDefinition, array $automatorConfig) {
    $entity->set($fieldDefinition->getName(), $values);
  }

  /**
   * If a json schema is set, it returns it.
   *
   * @return array|null
   *   The json schema.
   */
  public function getJsonSchema() {
    return !empty($this->jsonSchema) && count($this->jsonSchema) ? $this->jsonSchema : NULL;
  }

  /**
   * Ajax callback to load the models for the selected provider.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The form array.
   */
  public static function loadModelsAjaxCallback(array &$form, FormStateInterface $form_state) {
    $form_state->setRebuild(TRUE);
    // Get trigger suffix.
    $trigger = $form_state->getTriggeringElement();
    $suffix = $trigger['#attributes']['data-trigger-suffix'] ?? '';
    return $form['automator_container']['automator_advanced']['ajax_prefix' . $suffix];
  }

  /**
   * Load one extra provider form.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $formState
   *   The form state.
   * @param string $type
   *   The operation type.
   * @param string $suffix
   *   The suffix.
   * @param string $title
   *   The title.
   * @param array $defaultValues
   *   The default values.
   */
  public function extraProviderForm(&$form, FormStateInterface $formState, $type, $suffix, $title, $defaultValues = []) {
    $suffix = '_' . ltrim($suffix, '_');
    // Load the AI models.
    $providers = $this->formHelper->getAiProvidersOptions($type);
    $defaults = $this->aiPluginManager->getDefaultProviderForOperationType($type);
    $provider = $formState->getValue('automator_ai_provider' . $suffix);
    if (!$provider) {
      $provider = $defaultValues['automator_ai_provider' . $suffix] ?? $defaults['provider_id'];
    }

    $form['automator_ai_provider' . $suffix] = [
      '#type' => 'select',
      '#title' => $title,
      '#options' => $providers,
      '#default_value' => $provider,
      '#attributes' => [
        'data-trigger-suffix' => $suffix,
      ],
      '#ajax' => [
        'callback' => '\Drupal\ai_automators\PluginBaseClasses\RuleBase::loadModelsAjaxCallback',
        'wrapper' => 'provider_ajax_wrapper' . $suffix,
      ],
    ];
    $form['ajax_prefix' . $suffix] = [
      '#type' => 'details',
      '#open' => TRUE,
      '#title' => $this->t('Provider Configuration'),
      '#attributes' => [
        'id' => 'provider_ajax_wrapper' . $suffix,
      ],
      '#states' => [
        'visible' => [
          ':input[name="automator_ai_provider' . $suffix . '"]' => ['!value' => ''],
        ],
      ],
    ];

    if ($provider) {
      $llmInstance = $this->aiPluginManager->createInstance($provider);
      $model = $formState->getValue('automator_ai_model' . $suffix);
      if (!$model) {
        $model = $defaultValues['automator_ai_model' . $suffix] ?? $defaults['model_id'];
      }
      if (!$model) {
        $model = key($llmInstance->getConfiguredModels($type));
      }

      $form['ajax_prefix' . $suffix]['automator_ai_model' . $suffix] = [
        '#type' => 'select',
        '#title' => $this->t('Model'),
        // Only get chat models.
        '#options' => $llmInstance->getConfiguredModels($type),
        '#default_value' => $model,
        '#attributes' => [
          'data-trigger-suffix' => $suffix,
        ],
        '#ajax' => [
          'callback' => '\Drupal\ai_automators\PluginBaseClasses\RuleBase::loadModelsAjaxCallback',
          'wrapper' => 'provider_ajax_wrapper' . $suffix,
        ],
      ];

      if ($model) {
        $configuration = $llmInstance->getAvailableConfiguration($type, $model);

        if (count($configuration)) {
          $form['ajax_prefix' . $suffix]['ai_settings'] = [
            '#type' => 'fieldset',
            '#title' => $this->t('Settings'),
          ];
          foreach ($configuration as $key => $definition) {
            $set_key = 'automator_configuration_' . $key . $suffix;
            $form['ajax_prefix' . $suffix]['ai_settings'][$set_key]['#type'] = $this->formHelper->mapSchemaTypeToFormType($definition);
            $form['ajax_prefix' . $suffix]['ai_settings'][$set_key]['#required'] = $definition['required'] ?? FALSE;
            $form['ajax_prefix' . $suffix]['ai_settings'][$set_key]['#title'] = $definition['label'] ?? $key;
            $form['ajax_prefix' . $suffix]['ai_settings'][$set_key]['#description'] = $definition['description'] ?? '';
            $form['ajax_prefix' . $suffix]['ai_settings'][$set_key]['#default_value'] = $defaultValues[$set_key] ?? $definition['default'] ?? NULL;
            if (isset($definition['constraints'])) {
              foreach ($definition['constraints'] as $form_key => $value) {
                if ($form_key == 'options') {
                  $form['ajax_prefix' . $suffix]['ai_settings'][$set_key]['#options'] = array_combine($value, $value);
                  continue;
                }
                $form['ajax_prefix' . $suffix]['ai_settings'][$set_key]['#' . $form_key] = $value;
              }
            }
          }
        }
      }
    }

    return $form;
  }

  /**
   * Prepare LLM Instance.
   *
   * @param string $operationType
   *   The operation type.
   * @param array $automatorConfig
   *   The automator configuration.
   *
   * @return \Drupal\ai\Plugin\ProviderProxy
   *   The LLM instance.
   */
  public function prepareLlmInstance($operationType, array &$automatorConfig) {
    $provider = $this->getProvider($automatorConfig);
    $model = $this->getModel($automatorConfig);
    $instance = $this->aiPluginManager->createInstance($provider);

    // Get configuration.
    $config = [];
    $configCast = $instance->getAvailableConfiguration($operationType, $model);
    foreach ($automatorConfig as $key => $val) {
      if (strpos($key, 'configuration_') === 0 && $val) {
        $configKey = str_replace('configuration_', '', $key);
        if (isset($configCast[$configKey]['type'])) {
          $config[$configKey] = CastUtility::typeCast($configCast[$configKey]['type'], $val);
        }
      }
    }
    $instance->setConfiguration($config);
    return $instance;
  }

  /**
   * Run a chat message.
   *
   * @param string $prompt
   *   The prompt.
   * @param array $automatorConfig
   *   The automator configuration.
   * @param \Drupal\ai\Plugin\ProviderProxy $instance
   *   The LLM instance.
   * @param \Drupal\Core\Entity\ContentEntityInterface $entity
   *   The entity.
   *
   * @return array
   *   The response.
   */
  public function runChatMessage(string $prompt, array $automatorConfig, $instance, ?ContentEntityInterface $entity = NULL) {
    $text = $this->runRawChatMessage($prompt, $automatorConfig, $instance, $entity);

    // Normalize the response.
    $json = $this->promptJsonDecoder->decode($text);
    if (!is_array($json)) {
      throw new AiAutomatorResponseErrorException('The response was not a valid JSON response. The response was: ' . $text->getText());
    }
    if ($this->getJsonSchema()) {
      // Return as it is.
      return $this->promptJsonDecoder->decode($text)['values'] ?? [];
    }
    return $this->decodeValueArray($this->promptJsonDecoder->decode($text));
  }

  /**
   * Run a chat message.
   *
   * @param string $prompt
   *   The prompt.
   * @param array $automatorConfig
   *   The automator configuration.
   * @param \Drupal\ai\Plugin\ProviderProxy $instance
   *   The LLM instance.
   * @param \Drupal\Core\Entity\ContentEntityInterface|null $entity
   *   The entity.
   *
   * @return \Drupal\ai\OperationType\Chat\ChatMessage
   *   The response.
   */
  public function runRawChatMessage(string $prompt, array $automatorConfig, $instance, ?ContentEntityInterface $entity = NULL) {
    $images = [];
    // Check for images or media.
    $possibleImages = [];
    if (!empty($automatorConfig['configuration_image_field'])) {
      $parts[0] = $automatorConfig['configuration_image_field'];
      if (strpos($automatorConfig['configuration_image_field'], '--') !== FALSE) {
        $parts = explode('--', $automatorConfig['configuration_image_field']);
      }
      foreach ($entity->get($parts[0]) as $imageEntityWrapper) {
        $imageEntity = $imageEntityWrapper->entity;
        // If the image entity is not available, it might be partially formed.
        if (!$imageEntity) {
          $values = $imageEntityWrapper->getValue();
          // Try to load via the file ID.
          // @phpstan-ignore-next-line
          if (isset($values['target_id']) && $file = \Drupal::entityTypeManager()->getStorage('file')->load($values['target_id'])) {
            $imageEntity = $file;
          }
        }

        if (isset($parts[1])) {
          foreach ($imageEntity->get($parts[1]) as $image) {
            $possibleImages[] = $image->entity;
          }
        }
        else {
          $possibleImages[] = $imageEntity;
        }
      }
    }
    foreach ($possibleImages as $possibleImage) {
      // If an image style is set, use it.
      if (!empty($automatorConfig['configuration_image_style'])) {
        $possibleImage = $this->getGeneralHelper()->preprocessImageStyle($possibleImage, $automatorConfig['configuration_image_style']);
      }
      $image = new ImageFile();
      $image->setFileFromFile($possibleImage);
      $images[] = $image;
    }

    // Create new messages.
    $input = new ChatInput([
      new ChatMessage("user", $prompt, $images),
    ]);
    $input = $this->applyGuardrailsToInput($input, $automatorConfig);

    if ($this->getJsonSchema()) {
      $instance->setChatStructuredJsonSchema($this->getJsonSchema());
    }

    $model = $this->getModel($automatorConfig);
    // Check the field type of the field.
    $field_type = $entity ? $entity->get($automatorConfig['field_name'])->getFieldDefinition()->getType() : 'string';
    // If its text_long or text_with_summary, we filter host names.
    if (in_array($field_type, ['text_long', 'text_with_summary'])) {
      $input->setHostnameFilter(new HostnameFilterDto(plainTextMode: TRUE));
    }
    $response = $instance->chat($input, $model, $this->getTags($prompt, $automatorConfig, $instance, $entity))->getNormalized();
    $this->assertNotStoppedByGuardrail($input);

    return $response;
  }

  /**
   * Get the provider.
   *
   * @param array $automatorConfig
   *   The automator configuration.
   *
   * @return string
   *   The provider.
   */
  protected function getProvider(array $automatorConfig): string {
    if (empty($automatorConfig['ai_provider'])) {
      throw new AiAutomatorTypeNotRunnable('No provider set for the LLM type ' . $this->llmType);
    }
    $provider = $automatorConfig['ai_provider'];

    if ($provider === 'default_json') {
      $defaultProvider = $this->aiPluginManager->getDefaultProviderForOperationType('chat_with_complex_json');
      $provider = $defaultProvider['provider_id'] ?? NULL;
    }
    elseif ($provider === 'default_vision') {
      $defaultProvider = $this->aiPluginManager->getDefaultProviderForOperationType('chat_with_image_vision');
      $provider = $defaultProvider['provider_id'] ?? NULL;
    }
    elseif ($provider === 'default') {
      $defaultProvider = $this->aiPluginManager->getDefaultProviderForOperationType($this->llmType);
      $provider = $defaultProvider['provider_id'] ?? NULL;
    }

    // Ensure provider is always a valid string.
    if (empty($provider) || !is_string($provider)) {
      throw new AiAutomatorTypeNotRunnable('Invalid or missing AI provider for LLM type: ' . $this->llmType);
    }
    return $provider;
  }

  /**
   * Get the model.
   *
   * @param array $automatorConfig
   *   The automator configuration.
   *
   * @return string
   *   The model.
   */
  protected function getModel(array &$automatorConfig): string {
    if ($automatorConfig['ai_provider'] == 'default_json') {
      $automatorConfig['ai_model'] = $this->aiPluginManager->getDefaultProviderForOperationType('chat_with_complex_json')['model_id'];
    }
    elseif ($automatorConfig['ai_provider'] == 'default_structured_response') {
      $automatorConfig['ai_model'] = $this->aiPluginManager->getDefaultProviderForOperationType('chat_with_structured_response')['model_id'];
    }
    elseif ($automatorConfig['ai_provider'] == 'default_vision') {
      $automatorConfig['ai_model'] = $this->aiPluginManager->getDefaultProviderForOperationType('chat_with_image_vision')['model_id'];
    }
    elseif ($automatorConfig['ai_provider'] == 'default') {
      $automatorConfig['ai_model'] = $this->aiPluginManager->getDefaultProviderForOperationType($this->llmType)['model_id'];
    }
    return $automatorConfig['ai_model'];
  }

  /**
   * Decode a value array.
   *
   * @param mixed $json
   *   The input.
   *
   * @return array
   *   The decoded array.
   */
  public function decodeValueArray($json) {
    // Sometimes it doesn't become a valid JSON response, but many.
    if (isset($json[0]['value'])) {
      $values = [];
      foreach ($json as $val) {
        if (isset($val['value'])) {
          $values[] = $val['value'];
        }
      }
      return $values;
    }
    // Sometimes it sets the wrong key.
    elseif (isset($json[0])) {
      $values = [];
      foreach ($json as $val) {
        if (is_array($val) && isset($val[key($val)])) {
          $values[] = $val[key($val)];
        }
      }
      return $values;
    }
    // Sometimes it does not return with values in GPT 3.5.
    elseif (is_array($json) && isset($json[0][0])) {
      $values = [];
      foreach ($json as $vals) {
        foreach ($vals as $val) {
          if (isset($val)) {
            $values[] = $val;
          }
        }
      }
      return $values;
    }
    elseif (isset($json['value'])) {
      return [$json['value']];
    }
    return [];
  }

  /**
   * Generate the tags for the LLM request.
   *
   * This allows event subscribers to identify automator requests and responses
   * and can provide context such as the entity and field the automator is
   * attached to.
   *
   * @param string $prompt
   *   The prompt.
   * @param array $automatorConfig
   *   The automator configuration.
   * @param \Drupal\ai\Plugin\ProviderProxy $instance
   *   The LLM instance.
   * @param \Drupal\Core\Entity\ContentEntityInterface|null $entity
   *   The entity if available.
   *
   * @return string[]
   *   The array of tags for the LLM request.
   */
  public function getTags(string $prompt, array $automatorConfig, $instance, ?ContentEntityInterface $entity = NULL): array {
    // Always add an automator tag.
    $tags = [
      'ai_automator',
    ];

    // Add the rule being used as a tag.
    if (!empty($automatorConfig['rule'])) {
      $tags[] = 'ai_automator:type:' . $automatorConfig['rule'];
    }

    // Add some tags based on the entity & field name.
    if ($entity) {
      $tags[] = 'ai_automator:entity_type:' . $entity->getEntityTypeId();
      $tags[] = 'ai_automator:entity:' . $entity->id();
      $tags[] = 'ai_automator:bundle:' . $entity->bundle();
    }
    if (!empty($automatorConfig['field_name'])) {
      $tags[] = 'ai_automator:field_name:' . $automatorConfig['field_name'];
    }
    return $tags;
  }

}
