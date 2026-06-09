---
name: create-new-explorer
description: Scaffolds a new AiApiExplorer plugin for the AI API Explorer module. Verifies the operation type exists first, then generates the plugin class using AiProviderFormHelper::generateAiProvidersForm() for provider/model selection.
---

# Create New AI API Explorer Plugin

This skill scaffolds a new AiApiExplorer plugin for the Drupal AI API Explorer module. It generates a plugin class under `modules/ai_api_explorer/src/Plugin/AiApiExplorer/` that provides a form-based UI for testing an AI operation type.

## Step 0: Verify Operation Type Exists

**MANDATORY:** Before generating the explorer, verify that the operation type it targets actually exists.

Check that the interface file exists:
```
src/OperationType/{OperationName}/{OperationName}Interface.php
```

If the operation type does not exist, **stop** and inform the user:
> The operation type `{OperationName}` does not exist yet. Please create it first using the `/create-operation-type` skill, then re-run this skill.

Also read the interface to understand:
- The operation type ID (from the `#[OperationType]` attribute `id` field)
- The primary method name and its signature
- What the Input class constructor expects
- What the Output `getNormalized()` returns

If an Item class exists (e.g. `{OperationName}Item.php`), read it too - the explorer response display needs to render those items.

## Step 1: Gather Requirements

Ask the user for:

1. **Operation type name** (PascalCase, e.g. `TextClassification`, `ImageClassification`)
2. **Explorer title** (human-readable, e.g. "Text Classification Explorer")
3. **Explorer description** (e.g. "Contains a form where you can experiment and test the AI text classification.")
4. **What input fields does the form need?** - Derive from the Input class (e.g. text area for text, file upload for images, textarea for labels)
5. **How should the response be displayed?** - Derive from the Output class (e.g. table of items with labels/scores, text response, image display)

### Derive these automatically:

- **Plugin ID**: `{operation_type_id}_generator` (e.g. `text_classification_generator`)
- **Class name**: `{OperationName}Generator` (e.g. `TextClassificationGenerator`)
- **Operation type ID**: From the `#[OperationType]` attribute (e.g. `text_classification`)
- **Form prefix**: Short lowercase prefix for form element names (e.g. `text_class`, `moderation`, `image_class`)
- **AJAX wrapper ID**: Descriptive kebab-case ID (e.g. `text-classify-response`)

## Step 2: Generate the Explorer Plugin

Create `modules/ai_api_explorer/src/Plugin/AiApiExplorer/{OperationName}Generator.php`.

### Provider/model selection

Use `AiProviderFormHelper::generateAiProvidersForm()` from the base class's `$this->aiProviderHelper` to add provider and model selection dropdowns. This handles AJAX model loading automatically.

**In `buildForm()`** - add provider/model form elements with a short prefix (e.g. `text_class`, `moderation`):
```php
$this->aiProviderHelper->generateAiProvidersForm($form['left'], $form_state, '{operation_type_id}', '{prefix}', AiProviderFormHelper::FORM_CONFIGURATION_FULL);
$form['left']['{prefix}_ai_provider']['#ajax']['callback'] = $this::class . '::loadModelsAjaxCallback';
```

**In `getResponse()`** - get the provider instance and model from form state:
```php
$provider = $this->aiProviderHelper->generateAiProviderFromFormSubmit($form, $form_state, '{operation_type_id}', '{prefix}');
$model = $form_state->getValue('{prefix}_ai_model');
```

**In `normalizeCodeExample()`** - reference the provider/model from form state:
```php
$form_state->getValue('{prefix}_ai_provider')  // provider ID
$form_state->getValue('{prefix}_ai_model')      // model ID
```

The `{prefix}` must be a short, unique lowercase string (e.g. `text_class`, `image_class`, `moderation`). This prefix is used for the form element names: `{prefix}_ai_provider` and `{prefix}_ai_model`.

### Optional: pre-populate provider/model from the URL

Explorers can be deep-linked to with `?provider_id=...&model_id=...` query parameters (e.g. from the provider dashboard). To support this, read the query parameters at the start of `buildForm()` and seed the form state **before** calling `generateAiProvidersForm()`:

```php
$request = $this->getRequest();
if ($request->query->get('provider_id')) {
  $form_state->setValue('{prefix}_ai_provider', $request->query->get('provider_id'));
}
if ($request->query->get('model_id')) {
  $form_state->setValue('{prefix}_ai_model', $request->query->get('model_id'));
}
```

Add this block only if the new explorer should support URL-based pre-population. See `ImageClassificationGenerator` and `TextToImageGenerator` for working examples.

### Pattern for simple text-input explorers (like Moderation)

```php
<?php

declare(strict_types=1);

namespace Drupal\ai_api_explorer\Plugin\AiApiExplorer;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Service\AiProviderFormHelper;
use Drupal\ai_api_explorer\AiApiExplorerPluginBase;
use Drupal\ai_api_explorer\Attribute\AiApiExplorer;
{additional use statements for Input/Output/Item classes}

/**
 * Plugin implementation of the ai_api_explorer.
 */
#[AiApiExplorer(
  id: '{operation_type_id}_generator',
  title: new TranslatableMarkup('{Explorer Title}'),
  description: new TranslatableMarkup('{Explorer Description}'),
)]
final class {OperationName}Generator extends AiApiExplorerPluginBase {

  /**
   * {@inheritDoc}
   */
  public function isActive(): bool {
    return $this->providerManager->hasProvidersForOperationType('{operation_type_id}');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form = $this->getFormTemplate($form, '{ajax-wrapper-id}');

    // Input fields - tailor to the operation type's Input class.
    $form['left']['prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('{Input field description}'),
      '#description' => $this->t('{Help text}'),
      '#default_value' => '',
      '#required' => TRUE,
    ];

    // Load the LLM configurations.
    $this->aiProviderHelper->generateAiProvidersForm($form['left'], $form_state, '{operation_type_id}', '{prefix}', AiProviderFormHelper::FORM_CONFIGURATION_FULL);
    $form['left']['{prefix}_ai_provider']['#ajax']['callback'] = $this::class . '::loadModelsAjaxCallback';

    $form['left']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('{Submit Button Text}'),
      '#ajax' => [
        'callback' => $this->getAjaxResponseId(),
        'wrapper' => '{ajax-wrapper-id}',
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function getResponse(array &$form, FormStateInterface $form_state): array {
    try {
      $provider = $this->aiProviderHelper->generateAiProviderFromFormSubmit($form, $form_state, '{operation_type_id}', '{prefix}');

      // Validate input.
      $prompt = $form_state->getValue('prompt');
      if (empty($prompt)) {
        $form['right']['response']['#context']['ai_response'] = [
          'heading' => [
            '#type' => 'html_tag',
            '#tag' => 'h3',
            '#value' => $this->t('No Input Provided'),
          ],
          'message' => [
            '#type' => 'html_tag',
            '#tag' => 'div',
            '#value' => $this->t('Please provide input.'),
            '#attributes' => [
              'class' => ['ai-text-response', 'ai-error-message'],
            ],
          ],
        ];
        $form_state->setRebuild();
        return $form['right'];
      }

      // Build input and call the provider.
      $input = new {OperationName}Input($prompt);
      $response = $provider->{methodName}($input, $form_state->getValue('{prefix}_ai_model'), ['{operation_type_id}_explorer'])->getNormalized();

      // Display the response - tailor to the Output class.
      // ... (see response display patterns below)

      $form['right']['response']['#context']['ai_response']['code'] = $this->normalizeCodeExample($provider, $form_state, $prompt);
    }
    catch (\TypeError $e) {
      $form['right']['response']['#context']['ai_response'] = [
        'heading' => [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#value' => $this->t('Configuration Error'),
        ],
        'message' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#value' => $this->t('The AI provider could not be used. Please make sure a model is selected and the provider is properly configured.'),
          '#attributes' => [
            'class' => ['ai-text-response', 'ai-error-message'],
          ],
        ],
      ];
    }
    catch (\Exception $e) {
      $form['right']['response']['#context']['ai_response'] = [
        'heading' => [
          '#type' => 'html_tag',
          '#tag' => 'h3',
          '#value' => $this->t('Error'),
        ],
        'message' => [
          '#type' => 'html_tag',
          '#tag' => 'div',
          '#value' => $this->explorerHelper->renderException($e),
          '#attributes' => [
            'class' => ['ai-text-response', 'ai-error-message'],
          ],
        ],
      ];
    }

    $form_state->setRebuild();
    return $form['right'];
  }

  /**
   * Gets the normalized code example.
   *
   * @param \Drupal\ai\AiProviderInterface|\Drupal\ai\Plugin\ProviderProxy $provider
   *   The provider.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $prompt
   *   The prompt.
   *
   * @return array
   *   The normalized code example.
   */
  public function normalizeCodeExample(\Drupal\ai\AiProviderInterface|\Drupal\ai\Plugin\ProviderProxy $provider, FormStateInterface $form_state, string $prompt): array {
    $code = $this->getCodeExampleTemplate();
    if (count($provider->getConfiguration())) {
      $code['code']['#value'] .= $this->addProviderCodeExample($provider);
    }
    $code['code']['#value'] .= "\$ai_provider = \Drupal::service('ai.provider')->createInstance('" . $form_state->getValue('{prefix}_ai_provider') . "');<br>";
    if (count($provider->getConfiguration())) {
      $code['code']['#value'] .= "\$ai_provider->setConfiguration(\$config);<br>";
    }
    // Add input construction and method call code...
    $code['code']['#value'] .= "\$input = new \\Drupal\\ai\\OperationType\\{OperationName}\\{OperationName}Input('" . $prompt . "');<br>";
    $code['code']['#value'] .= "\$response = \$ai_provider->{methodName}(\$input, '" . $form_state->getValue('{prefix}_ai_model') . "', ['your_module_name'])->getNormalized();<br>";

    return $code;
  }

}
```

### Pattern for file-input explorers (like ImageClassification)

If the operation type requires file input, the explorer needs additional services and a file upload field:

```php
// Additional constructor parameters and create() services:
protected FileUrlGeneratorInterface $fileUrlGenerator,
protected FileSystemInterface $fileSystem

// In create():
$container->get('file_url_generator'),
$container->get('file_system'),

// File upload field in buildForm():
$form['left']['image'] = [
  '#type' => 'file',
  '#accept' => '.jpg, .jpeg, .png',
  '#title' => $this->t('Upload your image here.'),
  '#required' => TRUE,
];

// In getResponse(), use the base class helper:
$image_file = $this->generateFile('image');
```

## Response Display Patterns

### For Item-based output (classification results as table)

When the output `getNormalized()` returns an array of Item objects with `getLabel()` and `getConfidenceScore()`:

```php
$form['right']['response']['#context']['ai_response']['table'] = [
  '#type' => 'table',
  '#header' => [
    'label' => $this->t('Label'),
    'score' => $this->t('Score'),
  ],
  '#rows' => [],
  '#empty' => $this->t('There was an issue retrieving results.'),
];
foreach ($response as $row) {
  $form['right']['response']['#context']['ai_response']['table']['#rows'][] = [
    $this->t('<strong>:label</strong>', [':label' => $row->getLabel()]),
    $this->t('<em>:score</em>', [':score' => $row->getConfidenceScore()]),
  ];
}
```

### For simple text/object output

When the output `getNormalized()` returns a simple value or object:

```php
$form['right']['response']['#context']['ai_response']['response'] = [
  'result' => [
    '#type' => 'html_tag',
    '#tag' => 'p',
    '#value' => $this->t('Result: :result', [':result' => $response]),
  ],
];
```

### For ModerationResponse-style output

When the output returns a complex object with specific methods:

```php
$form['right']['response']['#context']['ai_response']['response'] = [
  'flag' => [
    '#type' => 'html_tag',
    '#tag' => 'h4',
    '#value' => $this->t('Got flagged: :result', [':result' => $response->isFlagged() ? 'Yes' : 'No']),
  ],
  'dump' => [
    '#type' => 'html_tag',
    '#tag' => 'p',
    '#value' => $this->t('Information dump:<pre>:dump</pre>', [':dump' => print_r($response->getInformation(), TRUE)]),
  ],
];
```

## Critical Rules

1. **Always verify the operation type exists** before generating any code.
2. **Use `AiProviderFormHelper::generateAiProvidersForm()`** for provider/model selection with a short prefix (e.g. `text_class`). Set the AJAX callback to `$this::class . '::loadModelsAjaxCallback'`.
3. **Extract provider from form** using `$this->aiProviderHelper->generateAiProviderFromFormSubmit($form, $form_state, '{operation_type_id}', '{prefix}')` and model via `$form_state->getValue('{prefix}_ai_model')`.
4. The plugin **MUST extend `AiApiExplorerPluginBase`**.
5. The plugin **MUST be a `final class`**.
6. The plugin **MUST implement `isActive()`** checking `$this->providerManager->hasProvidersForOperationType('{operation_type_id}')`.
7. The plugin **MUST implement `buildForm()`** and **`getResponse()`**.
8. The plugin **MUST include a `normalizeCodeExample()`** method that shows users example PHP code.
9. Error handling **MUST catch both `\TypeError` and `\Exception`** with proper error display.
10. The `getResponse()` method **MUST call `$form_state->setRebuild()`** and return `$form['right']` (or `$form['middle']` for three-column layouts).
11. The AJAX wrapper ID in `buildForm()` MUST match the ID used in `getFormTemplate()`.
12. Routes and menu links are **auto-generated** by `AiApiExplorerRouteSubscriber` - no manual routing needed.

## Reference Examples

Study these existing explorers for patterns:
- `modules/ai_api_explorer/src/Plugin/AiApiExplorer/ModerationGenerator.php` - simplest text-input explorer
- `modules/ai_api_explorer/src/Plugin/AiApiExplorer/ImageClassificationGenerator.php` - file-input explorer with table output and labels
- `modules/ai_api_explorer/src/Plugin/AiApiExplorer/TextToImageGenerator.php` - text-input with image output
- `modules/ai_api_explorer/src/AiApiExplorerPluginBase.php` - base class with all inherited methods
- `src/Service/AiProviderFormHelper.php` - the helper used via `$this->aiProviderHelper` for provider/model form generation

## Summary of Generated Files

| File | Purpose |
|------|---------|
| `modules/ai_api_explorer/src/Plugin/AiApiExplorer/{OperationName}Generator.php` | Explorer plugin class |

No routing, menu links, or permissions files need to be modified - the `AiApiExplorerRouteSubscriber` automatically discovers and registers routes for all explorer plugins.
