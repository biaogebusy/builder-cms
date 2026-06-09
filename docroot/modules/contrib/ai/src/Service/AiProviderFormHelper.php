<?php

namespace Drupal\ai\Service;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\ai\AiProviderInterface;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\Plugin\ProviderProxy;
use Drupal\ai\Utility\CastUtility;

/**
 * Helper class for modules that implements LLM Providers.
 */
class AiProviderFormHelper {

  use StringTranslationTrait;

  /**
   * Flag for getting no configurations.
   */
  const FORM_CONFIGURATION_NONE = 0;

  /**
   * Flag for getting the required configurations.
   */
  const FORM_CONFIGURATION_REQUIRED = 1;

  /**
   * Flag for getting the full configurations.
   */
  const FORM_CONFIGURATION_FULL = 2;

  /**
   * The LLM Providers plugin manager.
   *
   * @var \Drupal\ai\AiProviderPluginManager
   */
  protected $aiProviderPluginManager;

  /**
   * The current path.
   *
   * @var \Drupal\Core\Path\CurrentPathStack
   */
  protected $currentPath;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Constructs a new AiProviderHelper object.
   *
   * @param \Drupal\ai\AiProviderPluginManager $aiProviderPluginManager
   *   The LLM Providers plugin manager.
   * @param \Drupal\Core\Path\CurrentPathStack $currentPath
   *   The current path.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(AiProviderPluginManager $aiProviderPluginManager, CurrentPathStack $currentPath, MessengerInterface $messenger) {
    $this->aiProviderPluginManager = $aiProviderPluginManager;
    $this->currentPath = $currentPath;
    $this->messenger = $messenger;
  }

  /**
   * Helper function to generate a full list of available providers.
   *
   * @param array $form
   *   The form array to add the configuration to, passed by reference.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $operation_type
   *   The operation type.
   * @param string $prefix
   *   If you want to add a prefix to the form parts generated.
   * @param int $config_level
   *   What level of configuration you want to show.
   * @param int $weight
   *   The weight of the form element.
   * @param string $provider_id
   *   If you already have the provider id and only want to show the models.
   * @param string|\Drupal\Core\StringTranslation\TranslatableMarkup $title
   *   The title of the form element.
   * @param string|\Drupal\Core\StringTranslation\TranslatableMarkup $description
   *   The description of the form element.
   * @param bool $default_provider
   *   If a default provider should be selectable.
   */
  public function generateAiProvidersForm(
    array &$form,
    FormStateInterface $form_state,
    string $operation_type,
    string $prefix = '',
    int $config_level = AiProviderFormHelper::FORM_CONFIGURATION_NONE,
    int $weight = 0,
    string $provider_id = '',
    string|TranslatableMarkup $title = '',
    string|TranslatableMarkup $description = '',
    bool $default_provider = FALSE,
  ): array {
    $providers = $this->getAiProvidersOptions($operation_type);

    // Make sure the prefix is properly formatted.
    $prefix = $prefix ? rtrim($prefix, '_') . '_' : '';

    $defaults = $this->aiProviderPluginManager->getDefaultProviderForOperationType($operation_type);
    // If default provider exists and is allowed.
    if (!empty($defaults['provider_id']) && !empty($defaults['model_id']) && $default_provider) {
      $providers = ['__default__' => 'Default'] + $providers;
    }
    // Don't load the provider selection if a provider is already selected.
    $provider = $provider_id;
    if (!$provider_id) {
      $provider = $form_state->getValue($prefix . 'ai_provider');
      if (!$provider && !empty($defaults['provider_id'])) {
        $provider = $defaults['provider_id'];
      }

      $form[$prefix . 'ai_provider'] = [
        '#type' => 'select',
        '#title' => $title,
        '#options' => $providers,
        '#default_value' => $provider,
        '#description' => $description,
        '#required' => TRUE,
        '#empty_option' => ($provider) ? NULL : $this->t('Select a provider'),
        '#ajax' => [
          'callback' => '\Drupal\ai\Service\AiProviderFormHelper::loadModelsAjaxCallback',
          'wrapper' => $prefix . 'ajax_wrapper',
          'data-prefix' => $prefix,
        ],
      ];
      if ($weight) {
        $form[$prefix . 'ai_provider']['#weight'] = $weight;
      }
    }

    $form[$prefix . 'ajax_prefix'] = [
      '#type' => 'details',
      '#open' => $provider != '__default__',
      '#title' => $this->t('Provider Configuration'),
      '#attributes' => [
        'id' => $prefix . 'ajax_wrapper',
      ],
      '#states' => [
        'visible' => [
          ':input[name="' . $prefix . 'ai_provider"]' => ['!value' => $provider ? '__default__' : ''],
        ],
      ],
    ];
    if ($weight) {
      $form[$prefix . 'ajax_prefix']['#weight'] = $weight;
    }

    if ($provider && $provider != '__default__') {
      try {
        $llmInstance = $this->aiProviderPluginManager->createInstance($provider);
        $model = $form_state->getValue($prefix . 'ai_model');
        if (!$model && !empty($defaults['model_id'])) {
          $model = $defaults['model_id'];
        }
        $form[$prefix . 'ajax_prefix'][$prefix . 'ai_model'] = [
          '#type' => 'select',
          '#title' => $this->t('Model'),
          '#options' => $llmInstance->getConfiguredModels($operation_type),
          '#default_value' => $model,
          '#required' => TRUE,
          '#ajax' => [
            'callback' => '\Drupal\ai\Service\AiProviderFormHelper::loadModelsAjaxCallback',
            'wrapper' => $prefix . 'ajax_wrapper',
            'data-prefix' => $prefix,
            'event' => 'change',
          ],
        ];

        if ($model) {
          $configuration = $llmInstance->getAvailableConfiguration($operation_type, $model);
          $this->generateFormElements($prefix . 'ajax_prefix', $form, $config_level, $configuration);
        }
      }
      catch (\Exception $e) {
        $this->messenger->addWarning($this->t('The selected provider or model is not valid and has been reverted to the default. If no default is available, it has been cleared.'));
      }

    }

    return $form;
  }

  /**
   * Validate the LLM Provider form.
   *
   * @param array $form
   *   The form array to add the configuration to, passed by reference.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $operation_type
   *   The operation type.
   * @param string $prefix
   *   If you want to add a prefix to the form parts generated.
   */
  public function validateAiProvidersConfig(array &$form, FormStateInterface $form_state, string $operation_type, string $prefix): void {
    $prefix = $prefix ? rtrim($prefix, '_') . '_' : '';
    $provider = $form_state->getValue($prefix . 'ai_provider');
    $model = $form_state->getValue($prefix . 'ai_model');
    // Check the provider, unless it's the default.
    if ($provider && $provider != '__default__') {
      $llmInstance = $this->aiProviderPluginManager->createInstance($provider);
      if ($model) {
        $schema = $llmInstance->getAvailableConfiguration($operation_type, $model);
        foreach ($form_state->getValues() as $key => $value) {
          if (strpos($key, $prefix) === 0) {
            $real_key = trim(str_replace($prefix . 'ajax_prefix_configuration_', '', $key));
            if (!empty($schema[$real_key]['constraints'])) {
              if (!empty($schema[$real_key]['constraints']['min'])) {
                if ($value < $schema[$real_key]['constraints']['min']) {
                  $form_state->setErrorByName($key, $this->t('The value for @key must be at least @min.', [
                    '@key' => $schema[$real_key]['label'],
                    '@min' => $schema[$real_key]['constraints']['min'],
                  ]));
                }
              }
              if (!empty($schema[$real_key]['constraints']['max'])) {
                if ($value > $schema[$real_key]['constraints']['max']) {
                  $form_state->setErrorByName($key, $this->t('The value for @key must be at most @max.', [
                    '@key' => $schema[$real_key]['label'],
                    '@max' => $schema[$real_key]['constraints']['max'],
                  ]));
                }
              }
            }
          }
        }
      }
    }
  }

  /**
   * Generate a configured LLM Provider from the form values.
   *
   * @param array $form
   *   The form array to add the configuration to, passed by reference.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $operation_type
   *   The operation type.
   * @param string $prefix
   *   If you want to add a prefix to the form parts generated.
   *
   * @return \Drupal\ai\Provider\AiProviderInterface|\Drupal\ai\Plugin\ProviderProxy
   *   The provider instance or a proxy.
   */
  public function generateAiProviderFromFormSubmit(array &$form, FormStateInterface $form_state, string $operation_type, string $prefix): AiProviderInterface|ProviderProxy {
    $prefix = $prefix ? rtrim($prefix, '_') . '_' : '';
    $provider = $form_state->getValue($prefix . 'ai_provider');
    $configuration = $this->generateAiProvidersConfigurationFromForm($form, $form_state, $operation_type, $prefix);
    $provider = $this->aiProviderPluginManager->createInstance($provider);
    $provider->setConfiguration($configuration);
    return $provider;
  }

  /**
   * Generate a LLM configuration from the form values.
   *
   * @param array $form
   *   The form array to add the configuration to, passed by reference.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $operation_type
   *   The operation type.
   * @param string $prefix
   *   If you want to add a prefix to the form parts generated.
   *
   * @return array
   *   The configuration array.
   */
  public function generateAiProvidersConfigurationFromForm(array &$form, FormStateInterface $form_state, string $operation_type, string $prefix): array {
    // Make sure the prefix is properly formatted.
    $prefix = $prefix ? rtrim($prefix, '_') . '_' : '';
    $provider = $form_state->getValue($prefix . 'ai_provider');
    // If its the default provider, we don't need to do anything.
    if ($provider == '__default__') {
      return [];
    }
    $model = $form_state->getValue($prefix . 'ai_model');

    // Early return with an empty configuration if model is not selected.
    if (empty($model)) {
      // Set a more user-friendly message as a form error.
      $form_state->setErrorByName($prefix . 'ai_model', $this->t('Please select a model to continue. The AI provider may not be properly configured.'));
      return [];
    }

    $llmInstance = $this->aiProviderPluginManager->createInstance($provider);
    $schema = $llmInstance->getAvailableConfiguration($operation_type, $model);
    $prefix = $prefix ? rtrim($prefix, '_') . '_' : '';
    // Hopefully safe namespace.
    $prefix .= 'ajax_prefix_configuration_';
    $configuration = [];
    // We set and cast each value.
    foreach ($form_state->getValues() as $key => $value) {
      if (strpos($key, $prefix) === 0) {
        $real_key = trim(str_replace($prefix, '', $key));
        $type = $schema[$real_key]['type'] ?? 'string';
        $configuration[$real_key] = CastUtility::typeCast($type, trim($value));
        if ($type == 'boolean' || $type == 'bool') {
          $configuration[$real_key] = empty($value) || $value == 'false' ? FALSE : TRUE;
        }
      }
    }
    return $configuration;
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
    $prefix = $form_state->getTriggeringElement()['#ajax']['data-prefix'];
    $form_state->setRebuild();

    return self::findFormElementRecursive($form, $prefix . 'ajax_prefix');
  }

  /**
   * Recursive helper function to find a form element by name.
   *
   * @param array $value
   *   The form array.
   * @param string $form_element_name
   *   The form element name to find.
   *
   * @return array
   *   The found form element or an empty array.
   */
  public static function findFormElementRecursive(array &$value, string $form_element_name): array {
    if (isset($value[$form_element_name]['#type']) && $value[$form_element_name]['#type'] == 'details') {
      return $value[$form_element_name];
    }

    foreach ($value as $subvalue) {
      if (is_array($subvalue)) {
        $result = self::findFormElementRecursive($subvalue, $form_element_name);
        if ($result) {
          return $result;
        }
      }
    }

    return [];
  }

  /**
   * Helper function to generate a full options list of available LLM providers.
   *
   * @param string $operation_type
   *   The operation type.
   *
   * @return array
   *   The list of available LLM providers.
   */
  public function getAiProvidersOptions(string $operation_type) {
    $providers = $this->aiProviderPluginManager->getDefinitions();
    $options = [];
    foreach ($providers as $id => $provider) {
      // Check so its setup.
      $providerInstance = $this->aiProviderPluginManager->createInstance($id);
      if ($providerInstance->isUsable($operation_type)) {
        $options[$id] = $provider['label'];
      }

    }
    return $options;
  }

  /**
   * Helper function to generate form elements from schema.
   *
   * @param string $prefix
   *   Prefix for the form elements.
   * @param array $form
   *   The form.
   * @param int $config_level
   *   The config level to return.
   * @param array $schema
   *   Configuration schema of the provider.
   */
  private function generateFormElements(string $prefix, array &$form, int $config_level, array $schema): void {
    // If there isn't a configuration or shouldn't be, return.
    if (empty($schema) || $config_level == AiProviderFormHelper::FORM_CONFIGURATION_NONE) {
      return;
    }
    foreach ($schema as $key => $definition) {
      // We skip it if it's not required and we only want required.
      if ($config_level == AiProviderFormHelper::FORM_CONFIGURATION_REQUIRED && empty($definition['required'])) {
        continue;
      }
      $set_key = $prefix . '_configuration_' . $key;
      $form[$prefix][$set_key]['#type'] = $this->mapSchemaTypeToFormType($definition);
      $form[$prefix][$set_key]['#required'] = $definition['required'] ?? FALSE;
      $form[$prefix][$set_key]['#title'] = $definition['label'] ?? $key;
      $form[$prefix][$set_key]['#description'] = $definition['description'] ?? '';
      $form[$prefix][$set_key]['#default_value'] = $definition['default'] ?? NULL;
      if (isset($definition['constraints'])) {
        foreach ($definition['constraints'] as $form_key => $value) {
          if ($form_key == 'options') {
            $options = array_combine($value, $value);
            if (empty($definition['required'])) {
              $options = ['' => 'Select an option'] + $options;
            }
            $form[$prefix][$set_key]['#options'] = $options;
            continue;
          }
          $form[$prefix][$set_key]['#' . $form_key] = $value;
        }
      }
    }
  }

  /**
   * Maps schema data types to form element types.
   *
   * @param array $definition
   *   Data type of a configuration value.
   *
   * @return string
   *   Type of widget.
   */
  public function mapSchemaTypeToFormType(array $definition): string {
    // Check first for settings constraints.
    if (isset($definition['constraints']['options'])) {
      return 'select';
    }
    switch ($definition['type']) {
      case 'boolean':
        return 'checkbox';

      case 'int':
      case 'float':
        return 'textfield';

      case 'string_long':
        return 'textarea';

      case 'string':
      default:
        return 'textfield';
    }
  }

  /**
   * Helper function to expose the possibility to override/add/remove models.
   *
   * @param array $form
   *   The form array to add the table to.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param \Drupal\ai\AiProviderInterface|\Drupal\ai\Plugin\ProviderProxy $provider
   *   The provider.
   *
   * @return array
   *   The form array with the detail and table.
   */
  public function getModelsTable($form, FormStateInterface $form_state, AiProviderInterface|ProviderProxy $provider): array {
    $form['models'] = [
      '#type' => 'details',
      '#title' => $this->t('%provider Advanced Model Settings', [
        '%provider' => $provider->getPluginDefinition()['label'],
      ]),
      '#open' => !$provider->hasPredefinedModels(),
      '#description' => $this->t('Here you can see and if permitted add, remove or overwrite models for %provider.', [
        '%provider' => $provider->getPluginDefinition()['label'],
      ]),
    ];

    if (!$provider->hasPredefinedModels()) {

      $form['models']['actions'] = [
        '#type' => 'actions',
        '#weight' => -10,
      ];
    }

    $rows = [];
    $provider = $this->aiProviderPluginManager->createInstance($provider->getPluginId());
    $text = !$provider->hasPredefinedModels() ? $this->t('Edit') : $this->t('Overwrite');
    foreach ($this->aiProviderPluginManager->getOperationTypes() as $operation_type) {
      if ($provider->isUsable($operation_type['id'])) {
        foreach ($provider->getConfiguredModels($operation_type['id']) as $id => $model) {
          $rows[] = [
            $operation_type['label'],
            $id,
            $model,
            Link::fromTextAndUrl($text, Url::fromRoute('ai.edit_model_settings_form', [
              'operation_type' => $operation_type['id'],
              'provider' => $provider->getPluginId(),
              'model_id' => $id,
            ],
              [
                'query' => [
                  'destination' => $this->currentPath->getPath(),
                ],
              ])),
          ];
        }
        if (isset($form['models']['actions'])) {
          $form['models']['actions']['add_model_' . $operation_type['id']] = [
            '#type' => 'link',
            '#title' => $this->t('Add %operation_type Model', [
              '%operation_type' => $operation_type['label'],
            ]),
            '#url' => Url::fromRoute('ai.create_model_settings_form', [
              'operation_type' => $operation_type['id'],
              'provider' => $provider->getPluginId(),
            ],
              [
                'query' => [
                  'destination' => $this->currentPath->getPath(),
                ],
              ]),
            '#attributes' => [
              'class' => 'button',
            ],
          ];
        }
      }
    }
    if (count($rows)) {
      $form['models']['model_table'] = [
        '#type' => 'table',
        '#header' => [
          $this->t('Operation Type'),
          $this->t('Model ID'),
          $this->t('Label'),
          $this->t('Action'),
        ],
        '#rows' => $rows,
      ];
    }
    else {
      $form['models']['no_models'] = [
        '#markup' => $this->t('No models available. You may add models.'),
      ];
    }

    return $form['models'];
  }

}
