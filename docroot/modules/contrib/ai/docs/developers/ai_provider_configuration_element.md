# AI Provider Configuration Form Element

The `ai_provider_configuration` form element provides a standardized way for modules to select AI providers and models with optional advanced configuration. This element replaces the need for modules to implement their own provider selection logic, ensuring consistency across the Drupal AI ecosystem.

## Basic Usage

The simplest usage of the element requires only an operation type:

```php
$form['provider_config'] = [
  '#type' => 'ai_provider_configuration',
  '#title' => $this->t('AI Provider Configuration'),
  '#operation_type' => 'chat',
];
```

## Configuration Options

### Required Properties

- **`#operation_type`** (string, required): The operation type (e.g., 'chat', 'text_to_image') or pseudo operation type (e.g., 'chat_with_image_vision', 'chat_with_tools').

### Optional Properties

- **`#advanced_config`** (boolean, default: `TRUE`): Whether to show configuration fields for the selected model. When `TRUE`, the element will display provider-specific configuration options (e.g., temperature, max tokens) via AJAX when a provider/model is selected.

- **`#default_provider_allowed`** (boolean, default: `TRUE`): Whether to allow selecting a "Default" option. When `TRUE` and the AI settings define a default for the exact operation type passed to the element, a "Default" option will appear at the top of the dropdown (pseudo operation types must register their own default entry).

- **`#default_value`** (array, optional): Default value in the format:
  ```php
  [
    'provider' => 'provider_id',
    'model' => 'model_id',
    'config' => ['key' => 'value', ...], // Optional, only used if #advanced_config is TRUE
  ]
  ```

- **`#pseudo_operation_types`** (array, optional): Array of pseudo operation type definitions. If not provided, the element will check for hardcoded selections. Each pseudo operation type should have the structure:
  ```php
  [
    'id' => 'chat_with_image_vision',
    'actual_type' => 'chat',
    'label' => 'Chat with Image Vision',
    'filter' => [AiModelCapability::ChatWithImageVision],
  ]
  ```

- **`#empty_option`** (string|null, optional): Custom text for the empty option in the provider/model dropdown. If not provided and no value is selected, defaults to "- Select -". Set to `NULL` or empty string to hide the empty option.

- **`#empty_value`** (string|null, optional): The value to use for the empty option. Only used when `#empty_option` is set. Similar to the standard Select form element behavior.

- **`#inline_description`** (string|\Drupal\Core\StringTranslation\TranslatableMarkup|null, optional): Description text displayed between the provider/model dropdown and the configuration section. Use this for contextual help that should appear close to the selection dropdown. This is separate from `#description`, which appears at the bottom of the entire element.

## Return Value Structure

The form element returns a structured array when the form is submitted:

```php
[
  'provider' => 'provider_id',      // The selected provider ID
  'model' => 'model_id',            // The selected model ID
  'config' => [                     // Configuration array (empty if #advanced_config is FALSE or if "Default" option is selected)
    'temperature' => 0.7,
    'max_tokens' => 1000,
    // ... other configuration values
  ],
]
```

## Examples

### Basic Provider/Model Selection

```php
$form['provider_config'] = [
  '#type' => 'ai_provider_configuration',
  '#title' => $this->t('Select AI Provider'),
  '#description' => $this->t('Choose the AI provider and model to use.'),
  '#operation_type' => 'chat',
  '#advanced_config' => FALSE,  // Hide advanced configuration
  '#default_provider_allowed' => TRUE,
];
```

### With Advanced Configuration

```php
$form['provider_config'] = [
  '#type' => 'ai_provider_configuration',
  '#title' => $this->t('AI Provider with Configuration'),
  '#operation_type' => 'chat',
  '#advanced_config' => TRUE,  // Show configuration fields
  '#default_provider_allowed' => TRUE,
];
```

When configuration fields are displayed, they are generated from the provider's configuration schema. Each submitted value is type cast according to that schema, ensuring booleans, numeric ranges, and other constraints are respected.

### With Default Value

```php
$config = $this->config('my_module.settings');
$default_value = $config->get('provider_config');

$form['provider_config'] = [
  '#type' => 'ai_provider_configuration',
  '#title' => $this->t('AI Provider'),
  '#operation_type' => 'chat',
  '#default_value' => $default_value,  // Pre-populate the selection
];
```

### Using Pseudo Operation Types

Pseudo operation types are filtered variants of real operation types that apply capability filters. For example, `chat_with_image_vision` is a pseudo operation type that filters chat models to only those that support image vision capabilities.

```php
$form['provider_config'] = [
  '#type' => 'ai_provider_configuration',
  '#title' => $this->t('Chat with Image Vision'),
  '#operation_type' => 'chat_with_image_vision',  // Pseudo operation type
  '#advanced_config' => TRUE,
];
```

The element automatically:

- Maps `chat_with_image_vision` to the actual operation type `chat`
- Applies the `ChatWithImageVision` capability filter
- Uses `chat` when loading configuration schemas

#### Available Pseudo Operation Types

The AI module provides the following built-in pseudo operation types via `\Drupal\ai\Utility\PseudoOperationTypes`:

| ID | Actual Type | Capability Filter | Description |
|----|-------------|-------------------|-------------|
| `chat_with_image_vision` | `chat` | `ChatWithImageVision` | Analyze and interpret images within conversations |
| `chat_with_complex_json` | `chat` | `ChatJsonOutput` | Produce structured JSON outputs |
| `chat_with_structured_response` | `chat` | `ChatStructuredResponse` | Format responses into predictable structures |
| `chat_with_tools` | `chat` | `ChatTools` | Execute external functions or API calls |

You can retrieve these programmatically:

```php
use Drupal\ai\Utility\PseudoOperationTypes;

$pseudo_types = PseudoOperationTypes::getDefaultPseudoOperationTypes();
// Each type includes 'id', 'actual_type', 'label', 'filter', and 'description'.
```

### Custom Pseudo Operation Types

You can define custom pseudo operation types:

```php
use Drupal\ai\Enum\AiModelCapability;

$form['provider_config'] = [
  '#type' => 'ai_provider_configuration',
  '#title' => $this->t('Custom Filtered Chat'),
  '#operation_type' => 'my_custom_chat',
  '#pseudo_operation_types' => [
    [
      'id' => 'my_custom_chat',
      'actual_type' => 'chat',
      'label' => $this->t('Custom Chat Filter'),
      'description' => $this->t('A custom filter combining multiple capabilities.'),
      'filter' => [
        AiModelCapability::ChatTools,
        AiModelCapability::ChatStructuredResponse,
      ],
    ],
  ],
];
```

### Custom Empty Option

You can customize the empty option text and value:

```php
$form['provider_config'] = [
  '#type' => 'ai_provider_configuration',
  '#title' => $this->t('AI Provider'),
  '#operation_type' => 'chat',
  '#empty_option' => $this->t('Choose a provider...'),
  '#empty_value' => '',  // Optional, defaults to empty string
];
```

### Inline Description

Use `#inline_description` to display help text between the dropdown and the configuration section:

```php
$form['provider_config'] = [
  '#type' => 'ai_provider_configuration',
  '#title' => $this->t('AI Provider'),
  '#operation_type' => 'chat',
  '#inline_description' => $this->t('Select an AI provider and model for chat operations.'),
];
```

You can also combine `#inline_description` with `#description` for different contexts:

```php
$form['provider_config'] = [
  '#type' => 'ai_provider_configuration',
  '#title' => $this->t('AI Provider'),
  '#operation_type' => 'chat',
  '#inline_description' => $this->t('Select an AI provider and model.'),
  '#description' => $this->t('Advanced options for fine-tuning model behavior.'),
];
```

This renders:

1. Title/Label
2. Provider dropdown
3. "Select an AI provider and model." (inline_description)
4. Configuration section (expandable)
5. "Advanced configuration options are available..." (description)

## Default Value Handling

The element handles defaults in two stages:

1. **Initial selection**: if no `#default_value` is provided, the value callback resolves the default provider for the mapped actual operation type. Pseudo operation types automatically map to their `actual_type` during this lookup so their defaults mirror the underlying real operation type.
2. **Dropdown option**: the `Default` option is only added when a default provider is configured for the operation type string passed to the element. When a user selects this option, the value callback resolves the configured default and clears the `config` array.

Example: For `chat_with_image_vision`, a default defined for `chat` will be pre-selected on rebuild. To surface the "Default" option in the dropdown, you must also register a default for the `chat_with_image_vision` operation type.

## Migration Guide

### From Form Helper Service

**Before** (using `AiProviderFormHelper`):

```php
use Drupal\ai\Service\AiProviderFormHelper;

$form_helper = \Drupal::service('ai.form_helper');
$form_helper->generateAiProvidersForm(
  $form,
  $form_state,
  'chat',
  'prefix',
  AiProviderFormHelper::FORM_CONFIGURATION_FULL,
  0,
  '',
  $this->t('AI Provider'),
  $this->t('Description'),
  TRUE
);

// In validation:
$form_helper->validateAiProvidersConfig($form, $form_state, 'chat', 'prefix');

// In submit:
$provider = $form_helper->generateAiProviderFromFormSubmit($form, $form_state, 'chat', 'prefix');
$config = $form_helper->generateAiProvidersConfigurationFromForm($form, $form_state, 'chat', 'prefix');
```

**After** (using form element):

```php
$form['provider_config'] = [
  '#type' => 'ai_provider_configuration',
  '#title' => $this->t('AI Provider'),
  '#description' => $this->t('Description'),
  '#operation_type' => 'chat',
  '#advanced_config' => TRUE,
  '#default_provider_allowed' => TRUE,
];

// In submit:
$value = $form_state->getValue('provider_config');
$provider_id = $value['provider'];
$model_id = $value['model'];
$config = $value['config'];

$provider_manager = \Drupal::service('ai.provider');
$provider = $provider_manager->createInstance($provider_id);
$provider->setConfiguration($config);
```

### From Simple Options List

**Before** (using `getSimpleProviderModelOptions`):

```php
$form['provider_model'] = [
  '#type' => 'select',
  '#title' => $this->t('Provider/Model'),
  '#options' => \Drupal::service('ai.provider')->getSimpleProviderModelOptions('chat'),
];

// In submit:
$selected = $form_state->getValue('provider_model');
$provider_manager = \Drupal::service('ai.provider');
$provider = $provider_manager->loadProviderFromSimpleOption($selected);
$model = $provider_manager->getModelNameFromSimpleOption($selected);
```

**After** (using form element):

```php
$form['provider_config'] = [
  '#type' => 'ai_provider_configuration',
  '#title' => $this->t('Provider/Model'),
  '#operation_type' => 'chat',
  '#advanced_config' => FALSE,  // No advanced config needed
];

// In submit:
$value = $form_state->getValue('provider_config');
$provider_id = $value['provider'];
$model_id = $value['model'];
```

## Saving to Configuration

The AI module provides a standardized schema `ai.provider_config` for storing provider configurations. This ensures consistency across modules and enables future features like failover support.

### Define Your Schema

In your module's schema file (`my_module/config/schema/my_module.schema.yml`):

```yaml
my_module.settings:
  type: config_object
  label: 'My Module Settings'
  mapping:
    chat_provider:
      type: ai.provider_config
      label: 'Chat Provider Configuration'
    embeddings_provider:
      type: ai.provider_config
      label: 'Embeddings Provider Configuration'
```

### ConfigFormBase Example

Here's a complete example of a config form that saves provider configuration:

```php
<?php

namespace Drupal\my_module\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

class MyModuleSettingsForm extends ConfigFormBase {

  protected function getEditableConfigNames(): array {
    return ['my_module.settings'];
  }

  public function getFormId(): string {
    return 'my_module_settings_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('my_module.settings');

    // Load saved configuration and convert to element format.
    $chat_config = $config->get('chat_provider');
    $default_value = NULL;
    if ($chat_config && !empty($chat_config['provider_id'])) {
      $default_value = [
        'provider' => $chat_config['provider_id'],
        'model' => $chat_config['model_id'] ?? '',
        'config' => $chat_config['configuration'] ?? [],
      ];
    }

    $form['chat_provider'] = [
      '#type' => 'ai_provider_configuration',
      '#title' => $this->t('Chat Provider'),
      '#description' => $this->t('Select the AI provider for chat operations.'),
      '#operation_type' => 'chat',
      '#advanced_config' => TRUE,
      '#default_provider_allowed' => TRUE,
      '#default_value' => $default_value,
    ];

    return parent::buildForm($form, $form_state);
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $provider_config = $form_state->getValue('chat_provider');

    $this->config('my_module.settings')
      ->set('chat_provider', $provider_config)
      ->save();

    parent::submitForm($form, $form_state);
  }

}
```

### Schema Fields Reference

The `ai.provider_config` schema has the following fields:

| Field | Type | Description |
|-------|------|-------------|
| `use_default` | boolean | Whether to use the system's default provider for the operation type. |
| `provider` | string | The plugin ID of the AI provider (e.g., `openai`, `anthropic`). |
| `model` | string | The model identifier (e.g., `gpt-4`, `claude-3-opus`). |
| `config` | mapping | Provider-specific configuration parameters (e.g., temperature, max_tokens). |

### Default Configuration Example

In your module's install configuration (`my_module/config/install/my_module.settings.yml`):

```yaml
# Use system default provider
chat_provider:
  use_default: true
  provider: ''
  model: ''
  config: {}

# Use specific provider
embeddings_provider:
  use_default: false
  provider: 'openai'
  model: 'text-embedding-3-small'
  config:
    dimensions: 1536
```

### Loading Provider from Configuration

To instantiate a provider from saved configuration:

```php
$config = \Drupal::config('my_module.settings');
$provider_config = $config->get('chat_provider');

/** @var \Drupal\ai\AiProviderPluginManager $provider_manager */
$provider_manager = \Drupal::service('ai.provider');

if (!empty($provider_config['use_default'])) {
  // Use the default provider for the operation type.
  $default = $provider_manager->getDefaultProviderForOperationType('chat');
  $provider_id = $default['provider_id'];
  $model_id = $default['model_id'];
  $configuration = [];
}
else {
  $provider_id = $provider_config['provider'];
  $model_id = $provider_config['model'];
  $configuration = $provider_config['config'] ?? [];
}

if ($provider_id) {
  $provider = $provider_manager->createInstance($provider_id);
  $provider->setConfiguration($configuration);
  // Now use $provider with $model_id for your AI operations.
}
```

For more details about the configuration schema, see the [Provider Config Schema](provider_config_schema.md) documentation.

