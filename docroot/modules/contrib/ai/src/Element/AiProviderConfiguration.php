<?php

declare(strict_types=1);

namespace Drupal\ai\Element;

use Drupal\ai\AiProviderInterface;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\Utility\CastUtility;
use Drupal\ai\Utility\PseudoOperationTypes;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Attribute\FormElement;
use Drupal\Core\Render\Element\FormElementBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Form element for selecting AI provider and model configuration.
 *
 * @see \Drupal\Core\Render\Element\FormElementBase
 */
#[FormElement('ai_provider_configuration')]
class AiProviderConfiguration extends FormElementBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    return [
      '#process' => [
        [static::class, 'processElement'],
      ],
      "#tree" => TRUE,
      '#value_callback' => [static::class, 'valueCallback'],
      '#theme_wrappers' => ['form_element'],
      '#operation_type' => '',
      '#advanced_config' => TRUE,
      '#default_provider_allowed' => TRUE,
      '#pseudo_operation_types' => [],
      '#empty_option' => NULL,
      '#empty_value' => NULL,
      '#inline_description' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    /** @var \Drupal\ai\AiProviderPluginManager $provider_manager */
    $provider_manager = \Drupal::service('ai.provider');

    if ($input === FALSE) {
      // Form is being rebuilt, return the current value.
      $default_value = $element['#default_value'] ?? NULL;
      if (is_array($default_value) && isset($default_value['provider']) && isset($default_value['model'])) {
        return $default_value;
      }

      // Try to get default provider for operation type.
      $operation_type = $element['#operation_type'] ?? '';
      if (!empty($operation_type)) {
        // For pseudo operation types, check default using actual_type.
        $pseudo_operation_type = static::getPseudoOperationType($element, $operation_type);
        $default_operation_type = $pseudo_operation_type ? $pseudo_operation_type['actual_type'] : $operation_type;
        $default = $provider_manager->getDefaultProviderForOperationType($default_operation_type);
        if (!empty($default['provider_id']) && !empty($default['model_id'])) {
          return [
            'use_default' => TRUE,
            'provider' => '',
            'model' => '',
            'config' => [],
          ];
        }
      }

      return [
        'use_default' => FALSE,
        'provider' => '',
        'model' => '',
        'config' => [],
      ];
    }

    // Get the selected value from the dropdown.
    // The $input parameter from FormBuilder::handleInputElement() is already
    // scoped to $element['#parents'] — it is the raw POST data at that path.
    // Therefore we must use relative keys (e.g. ['provider_model']) to index
    // into $input, NOT full #parents-based paths.
    $selected_value = '';
    if (is_array($input)) {
      // Input is an array structure like
      // ['provider_model' => 'provider__model', 'config' => [...]].
      $selected_value = $input['provider_model'] ?? '';
    }
    elseif (is_string($input)) {
      // If input is a string (legacy/fallback), use it directly.
      $selected_value = $input;
    }

    // Handle "Default" option.
    if ($selected_value === AiProviderInterface::DEFAULT_MODEL_VALUE) {
      $operation_type = $element['#operation_type'] ?? '';
      if (!empty($operation_type)) {
        $default = $provider_manager->getDefaultProviderForOperationType($operation_type);
        if (!empty($default['provider_id']) && !empty($default['model_id'])) {
          return [
            'use_default' => TRUE,
            'provider' => '',
            'model' => '',
            'config' => [],
          ];
        }
      }
      return [
        'provider' => '',
        'model' => '',
        'config' => [],
        'use_default' => TRUE,
      ];
    }

    // Extract provider and model from simple option format
    // (provider_id__model_id).
    if (empty($selected_value)) {
      return [
        'use_default' => FALSE,
        'provider' => '',
        'model' => '',
        'config' => [],
      ];
    }

    $parts = explode('__', $selected_value);
    if (count($parts) !== 2) {
      return [
        'use_default' => FALSE,
        'provider' => '',
        'model' => '',
        'config' => [],
      ];
    }

    $provider_id = $parts[0];
    $model_id = $parts[1];

    // Get configuration if advanced_config is enabled.
    $config = [];
    $advanced_config = $element['#advanced_config'] ?? TRUE;
    if ($advanced_config) {
      // Get config from the input array structure.
      // $input is already scoped to $element['#parents'], so use relative key.
      $config_input = [];
      if (is_array($input)) {
        $config_input = $input['config'] ?? [];
      }

      // Fallback to user input if not found in $input.
      // getUserInput() contains the full form input tree, so use full path.
      if (empty($config_input)) {
        $user_input = $form_state->getUserInput();
        $config_parents = array_merge($element['#parents'], ['config']);
        $config_input = NestedArray::getValue($user_input, $config_parents) ?? [];
      }

      if (is_array($config_input)) {
        // Get the configuration schema to properly cast values.
        try {
          $provider = $provider_manager->createInstance($provider_id);
          $operation_type = static::getActualOperationType($element);
          $schema = $provider->getAvailableConfiguration($operation_type, $model_id);

          foreach ($config_input as $key => $value) {
            if (isset($schema[$key])) {
              $type = $schema[$key]['type'] ?? 'string';
              $config[$key] = CastUtility::typeCast($type, trim($value));
              if ($type === 'boolean' || $type === 'bool') {
                $config[$key] = !(empty($value) || $value === 'false');
              }
            }
          }
        }
        catch (\Exception $e) {
          // If provider/model is not available, return empty config.
        }
      }
    }

    return [
      'use_default' => FALSE,
      'provider' => $provider_id,
      'model' => $model_id,
      'config' => $config,
    ];
  }

  /**
   * Process the form element.
   *
   * @param array $element
   *   The element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $complete_form
   *   The complete form.
   *
   * @return array
   *   The processed element.
   */
  public static function processElement(array &$element, FormStateInterface $form_state, array &$complete_form): array {
    $operation_type = $element['#operation_type'] ?? '';
    if (empty($operation_type)) {
      $element['#markup'] = new TranslatableMarkup('Operation type is required.');
      return $element;
    }

    /** @var \Drupal\ai\AiProviderPluginManager $provider_manager */
    $provider_manager = \Drupal::service('ai.provider');

    // Get pseudo operation type definition if applicable.
    $pseudo_operation_type = static::getPseudoOperationType($element, $operation_type);
    $actual_operation_type = static::getActualOperationType($element);
    $filters = static::getOperationTypeFilters($element, $operation_type);

    // Get provider/model options.
    $options = $provider_manager->getSimpleProviderModelOptions(
      $actual_operation_type,
      FALSE,
      TRUE,
      $filters
    );

    // Add "Default" option if allowed.
    $default_provider_allowed = $element['#default_provider_allowed'] ?? TRUE;
    if ($default_provider_allowed) {
      $default = $provider_manager->getDefaultProviderForOperationType($operation_type);
      if (!empty($default['provider_id']) && !empty($default['model_id'])) {
        $options = [AiProviderInterface::DEFAULT_MODEL_VALUE => new TranslatableMarkup('Default')] + $options;
      }
    }

    // Determine default selected value.
    $default_value = $element['#default_value'] ?? NULL;
    $selected_value = '';
    if (is_array($default_value) && isset($default_value['provider']) && isset($default_value['model'])) {
      if (!empty($default_value['use_default']) && $default_provider_allowed) {
        $selected_value = AiProviderInterface::DEFAULT_MODEL_VALUE;
      }
      elseif ($default_value['provider'] !== '' || $default_value['model'] !== '') {
        $selected_value = $default_value['provider'] . '__' . $default_value['model'];
      }
    }
    else {
      // Try to get default provider for operation type.
      // For pseudo operation types, check default using actual_type.
      $default_operation_type = $pseudo_operation_type ? $pseudo_operation_type['actual_type'] : $operation_type;
      $default = $provider_manager->getDefaultProviderForOperationType($default_operation_type);
      if (!empty($default['provider_id']) && !empty($default['model_id'])) {
        if ($default_provider_allowed) {
          // Use DEFAULT_MODEL_VALUE if default provider is allowed.
          $selected_value = AiProviderInterface::DEFAULT_MODEL_VALUE;
        }
        else {
          // Use the actual provider__model format.
          $selected_value = $default['provider_id'] . '__' . $default['model_id'];
        }
      }
    }

    // Generate wrapper ID for AJAX replacement.
    $config_wrapper_id = static::getConfigWrapperId($element['#parents']);
    $config_parents = array_merge($element['#parents'], ['config']);

    // Build the provider/model select dropdown.
    // Use nested parents so the element value can be an array structure.
    $select_element = [
      '#type' => 'select',
      '#options' => $options,
      '#default_value' => $selected_value,
      '#required' => $element['#required'] ?? FALSE,
      '#ajax' => [
        'callback' => [static::class, 'ajaxCallback'],
        'wrapper' => $config_wrapper_id,
        'event' => 'change',
      ],
      '#parents' => array_merge($element['#parents'], ['provider_model']),
    ];

    // Support #empty_option property, similar to Select form element.
    // If explicitly set use it, otherwise fall back to default behavior.
    if (isset($element['#empty_option'])) {
      $select_element['#empty_option'] = $element['#empty_option'];
      // Also support #empty_value if provided.
      if (isset($element['#empty_value'])) {
        $select_element['#empty_value'] = $element['#empty_value'];
      }
    }
    elseif (!$selected_value) {
      // Default behavior: show empty option if no value is selected.
      $select_element['#empty_option'] = new TranslatableMarkup('- Select -');
    }

    $element['provider_model'] = $select_element;
    $element['provider_model']['#weight'] = 0;
    $element['#after_build'][] = [static::class, 'afterBuild'];

    // Add inline description if provided.
    $inline_description = $element['#inline_description'] ?? NULL;
    if (!empty($inline_description)) {
      $element['inline_description'] = [
        '#type' => 'container',
        '#attributes' => [
          'class' => ['form-item__description', 'ai-provider-inline-description'],
        ],
        '#weight' => 5,
        'content' => [
          '#markup' => $inline_description,
        ],
      ];
    }

    // Build configuration container (shown if advanced_config is TRUE).
    $advanced_config = $element['#advanced_config'] ?? TRUE;
    if ($advanced_config) {

      // Determine if config container should be open.
      // Keep it closed when default provider is selected.
      $is_default = ($selected_value === AiProviderInterface::DEFAULT_MODEL_VALUE);
      $config_open = !$is_default;

      $element['config'] = [
        '#type' => 'details',
        '#title' => new TranslatableMarkup('Configuration'),
        '#id' => $config_wrapper_id,
        '#parents' => $config_parents,
        '#open' => $config_open,
        '#weight' => 10,
      ];

      // Load configuration fields if provider/model is selected.
      if (!empty($selected_value)) {
        // Get default config from element's #default_value if available.
        $default_config = [];
        if (is_array($default_value) && isset($default_value['config']) && is_array($default_value['config'])) {
          $default_config = $default_value['config'];
        }

        if ($is_default) {
          $default_operation_type = $pseudo_operation_type ? $pseudo_operation_type['actual_type'] : $operation_type;
          $default = $provider_manager->getDefaultProviderForOperationType($default_operation_type);
          if (!empty($default['provider_id']) && !empty($default['model_id'])) {
            static::loadConfigurationFields($element['config'], $default['provider_id'], $default['model_id'], $actual_operation_type, $default_config, $provider_manager);
          }
        }
        else {
          $parts = explode('__', $selected_value);
          if (count($parts) === 2) {
            $provider_id = $parts[0];
            $model_id = $parts[1];
            static::loadConfigurationFields($element['config'], $provider_id, $model_id, $actual_operation_type, $default_config, $provider_manager);
          }
        }
      }
    }

    return $element;
  }

  /**
   * After build callback to remove the internal widget key from form state.
   *
   * The provider_model child element stores its raw "provider__model" string
   * at the parent's value path, which would otherwise leak into the saved
   * configuration object. Strip it after all child elements are processed.
   */
  public static function afterBuild(array $element, FormStateInterface $form_state): array {
    if ($form_state->isProcessingInput()) {
      $values = $form_state->getValue($element['#parents']);
      if (\is_array($values) && \array_key_exists('provider_model', $values)) {
        unset($values['provider_model']);
        $form_state->setValue($element['#parents'], $values);
      }
    }
    return $element;
  }

  /**
   * AJAX callback to load configuration fields.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The configuration container.
   */
  public static function ajaxCallback(array &$form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $parents = $triggering_element['#array_parents'];
    array_pop($parents);
    $element = NestedArray::getValue($form, $parents);

    // Get the selected value from user input.
    $user_input = $form_state->getUserInput();
    $element_parents = $triggering_element['#parents'];
    array_pop($element_parents);
    $select_parents = array_merge($element_parents, ['provider_model']);
    $selected_value = NestedArray::getValue($user_input, $select_parents) ?? '';
    $selected_value = is_string($selected_value) ? $selected_value : (string) $selected_value;

    $operation_type = $element['#operation_type'] ?? '';
    $actual_operation_type = static::getActualOperationType($element);

    // Get default config from element's #default_value or existing user input.
    $default_config = [];
    $default_value = $element['#default_value'] ?? NULL;
    if (is_array($default_value) && isset($default_value['config']) && is_array($default_value['config'])) {
      $default_config = $default_value['config'];
    }
    else {
      $config_parents = array_merge($element_parents, ['config']);
      $existing_config = NestedArray::getValue($user_input, $config_parents) ?? [];
      if (is_array($existing_config)) {
        $default_config = $existing_config;
      }
    }

    // Completely remove existing configuration from the form array.
    $config_parents_in_form = array_merge($parents, ['config']);
    NestedArray::unsetValue($form, $config_parents_in_form);

    // Generate wrapper ID and create fresh config container.
    $config_wrapper_id = static::getConfigWrapperId($element['#parents']);
    $config_element_parents = array_merge($element['#parents'], ['config']);

    // Determine if config container should be open.
    // Keep it closed when default provider is selected.
    $is_default = ($selected_value === AiProviderInterface::DEFAULT_MODEL_VALUE);
    $config_open = !$is_default;

    $config_element = [
      '#type' => 'details',
      '#title' => new TranslatableMarkup('Configuration'),
      '#id' => $config_wrapper_id,
      '#parents' => $config_element_parents,
      '#open' => $config_open,
    ];

    // Load configuration if provider/model is selected.
    /** @var \Drupal\ai\AiProviderPluginManager $provider_manager */
    $provider_manager = \Drupal::service('ai.provider');
    if (!empty($selected_value)) {
      if ($is_default) {
        $pseudo_operation_type = static::getPseudoOperationType($element, $operation_type);
        $default_operation_type = $pseudo_operation_type ? $pseudo_operation_type['actual_type'] : $operation_type;
        $default = $provider_manager->getDefaultProviderForOperationType($default_operation_type);
        if (!empty($default['provider_id']) && !empty($default['model_id'])) {
          static::loadConfigurationFields($config_element, $default['provider_id'], $default['model_id'], $actual_operation_type, $default_config, $provider_manager);
        }
      }
      else {
        $parts = explode('__', $selected_value);
        if (count($parts) === 2) {
          $provider_id = $parts[0];
          $model_id = $parts[1];
          static::loadConfigurationFields($config_element, $provider_id, $model_id, $actual_operation_type, $default_config, $provider_manager);
        }
      }
    }

    // Update the form array directly with the new config element.
    NestedArray::setValue($form, $config_parents_in_form, $config_element);

    $form_state->setRebuild();
    return $config_element;
  }

  /**
   * Load configuration fields for a provider/model.
   *
   * @param array $container
   *   The container element to populate.
   * @param string $provider_id
   *   The provider ID.
   * @param string $model_id
   *   The model ID.
   * @param string $operation_type
   *   The actual operation type.
   * @param array $default_config
   *   Optional default configuration values from element's #default_value.
   * @param \Drupal\ai\AiProviderPluginManager|null $provider_manager
   *   Optional provider manager service. If not provided, will be loaded.
   */
  protected static function loadConfigurationFields(array &$container, string $provider_id, string $model_id, string $operation_type, array $default_config = [], ?AiProviderPluginManager $provider_manager = NULL): void {
    try {
      if ($provider_manager === NULL) {
        /** @var \Drupal\ai\AiProviderPluginManager $provider_manager */
        $provider_manager = \Drupal::service('ai.provider');
      }
      $provider = $provider_manager->createInstance($provider_id);
      $schema = $provider->getAvailableConfiguration($operation_type, $model_id);

      if (empty($schema)) {
        return;
      }

      // Generate form elements from schema.
      foreach ($schema as $key => $definition) {
        // Use default config value if provided, otherwise fall back to
        // schema default.
        $field_value = $default_config[$key] ?? ($definition['default'] ?? NULL);
        $description = $definition['description'] ?? '';
        $container[$key] = [
          '#type' => static::mapSchemaTypeToFormType($definition),
          '#title' => $definition['label'] ?? $key,
          '#required' => $definition['required'] ?? FALSE,
          '#value' => $field_value,
        ];

        // Set description and description_display if description exists.
        if (!empty($description)) {
          $container[$key]['#description'] = $description;
          $container[$key]['#description_display'] = 'after';
        }

        // Handle constraints.
        if (isset($definition['constraints'])) {
          foreach ($definition['constraints'] as $constraint_key => $value) {
            if ($constraint_key === 'options') {
              $options = array_combine($value, $value);
              if (empty($definition['required'])) {
                $container[$key]['#empty_option'] = new TranslatableMarkup('Select an option');
                $container[$key]['#empty_value'] = '';
              }
              $container[$key]['#options'] = $options;
            }
            else {
              $container[$key]['#' . $constraint_key] = $value;
            }
          }
        }
      }
    }
    catch (\Exception $e) {
      // If provider/model is not available, don't load configuration.
      \Drupal::logger('ai')->error('Failed to load configuration fields for provider @provider, model @model, operation type @operation_type: @message', [
        '@provider' => $provider_id,
        '@model' => $model_id,
        '@operation_type' => $operation_type,
        '@message' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Get the pseudo operation type definition if applicable.
   *
   * @param array $element
   *   The element.
   * @param string $operation_type
   *   The operation type ID.
   *
   * @return array|null
   *   The pseudo operation type definition or NULL.
   */
  protected static function getPseudoOperationType(array $element, string $operation_type): ?array {
    // Check if pseudo operation types are provided in element.
    $pseudo_types = $element['#pseudo_operation_types'] ?? [];
    foreach ($pseudo_types as $pseudo_type) {
      if (isset($pseudo_type['id']) && $pseudo_type['id'] === $operation_type) {
        return $pseudo_type;
      }
    }

    // Check default pseudo operation types.
    $default_pseudo_types = PseudoOperationTypes::getDefaultPseudoOperationTypes();
    foreach ($default_pseudo_types as $pseudo_type) {
      if (isset($pseudo_type['id']) && $pseudo_type['id'] === $operation_type) {
        return $pseudo_type;
      }
    }

    return NULL;
  }

  /**
   * Get the actual operation type (handles pseudo operation types).
   *
   * @param array $element
   *   The element.
   *
   * @return string
   *   The actual operation type.
   */
  protected static function getActualOperationType(array $element): string {
    $operation_type = $element['#operation_type'] ?? '';
    $pseudo_type = static::getPseudoOperationType($element, $operation_type);
    if ($pseudo_type && isset($pseudo_type['actual_type'])) {
      return $pseudo_type['actual_type'];
    }
    return $operation_type;
  }

  /**
   * Get filters for the operation type.
   *
   * @param array $element
   *   The element.
   * @param string $operation_type
   *   The operation type ID.
   *
   * @return array
   *   Array of capability filters.
   */
  protected static function getOperationTypeFilters(array $element, string $operation_type): array {
    $pseudo_type = static::getPseudoOperationType($element, $operation_type);
    if ($pseudo_type && isset($pseudo_type['filter'])) {
      return $pseudo_type['filter'];
    }
    return [];
  }

  /**
   * Maps schema data types to form element types.
   *
   * @param array $definition
   *   Data type definition of a configuration value.
   *
   * @return string
   *   Type of form element widget.
   */
  protected static function mapSchemaTypeToFormType(array $definition): string {
    // Check first for settings constraints.
    if (isset($definition['constraints']['options'])) {
      return 'select';
    }

    return match ($definition['type'] ?? 'string') {
      'boolean' => 'checkbox',
      'int', 'float' => 'textfield',
      'string_long' => 'textarea',
      'string' => 'textfield',
      default => 'textfield',
    };
  }

  /**
   * Generates the wrapper ID for the config element.
   *
   * Uses Drupal's standard pattern to ensure consistency across AJAX requests.
   *
   * @param array $element_parents
   *   The parent element's #parents array.
   *
   * @return string
   *   The wrapper ID.
   */
  protected static function getConfigWrapperId(array $element_parents): string {
    $config_parents = array_merge($element_parents, ['config']);
    return 'edit-' . implode('-', $config_parents);
  }

}
