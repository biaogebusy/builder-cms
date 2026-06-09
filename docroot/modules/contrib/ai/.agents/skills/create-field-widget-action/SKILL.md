---
name: create-field-widget-action
description: Scaffolds a new FieldWidgetAction plugin for the Drupal AI field_widget_actions module. Asks whether the action uses AJAX callbacks or a modal form, then generates the plugin class with the appropriate base class, methods, and configuration.
---

# Create Field Widget Action Plugin

This skill scaffolds a new FieldWidgetAction plugin for the Drupal AI module.
Field Widget Action plugins provide action buttons on field widgets in entity
forms — they can auto-generate content, suggest values, transform field data,
or open modal dialogs for more complex interactions.

## Step 0: Determine Action Type

**MANDATORY:** Before gathering any other requirements, ask the user to clarify
which type of action they need. This determines the base class and methods.

### AJAX vs Modal Form

Ask the user:

> **Does this action use a direct AJAX callback or a modal form?**
>
> - **AJAX callback** actions run logic server-side and return results
>   directly. They extend `FieldWidgetActionBase` and implement
>   `getAjaxCallback()` to name their callback method. Use this for
>   simple operations like AI text suggestions, autofill, or one-click
>   transformations.
>   *Example: `SuggestTextAction` — calls an AI provider and returns
>   suggestions via `returnSuggestions()`.*
>
> - **Modal form** actions open a dialog where the user makes choices
>   before the action runs. They extend `FieldWidgetFormActionBase` and
>   implement `buildModalForm()` + `submitModalFormFillFields()`. Use
>   this when the action needs user input (tone selection, prompt
>   editing, option picking) before processing.
>   *Example: `FillTextTestAction` — opens a modal to collect text and
>   a repeat count, then fills the field.*
>
> Which type does your action need?

Wait for the user's answer before proceeding.

## Step 1: Gather Requirements

Ask the user for:

1. **Plugin ID** (snake_case, e.g. `my_module_suggest_text`,
   `document_loader_fwa`)
2. **Plugin label** (human-readable, e.g. "AI Text Suggestion",
   "Load Document")
3. **Plugin description** (e.g. "Suggests text using AI.")
4. **What does the action do?** — The core logic
5. **Which widget types does it target?** (e.g. `string_textfield`,
   `text_textarea`, `file_generic`, `media_library_widget`)
6. **Which field types does it target?** (e.g. `string`, `text_long`,
   `file`, `entity_reference`)
7. **Category** — grouping label in the configuration UI
8. **Per-delta or whole-field?** — `multiple: TRUE` shows button per
   delta; `FALSE` shows one button for the entire field
9. **Does it need a configuration form?** — admin-configurable settings
   like prompts, thresholds, destination fields
10. **Does it need dependency injection?** — services like `ai.provider`,
    `entity_type.manager`, `logger.factory`

### For modal form actions, also ask:

11. **What form fields does the modal present?** — select lists, text
    areas, checkboxes
12. **How does the modal submission map to field values?** — which Ajax
    commands fill which fields

## Step 2: Generate the Plugin

Create the plugin file at
`src/Plugin/FieldWidgetAction/{ClassName}.php` in the target module.

### Pattern A: AJAX callback action (direct, no modal)

For actions that process directly and return results. This is the simpler
and more common pattern.

**Extends:** `FieldWidgetActionBase`

```php
<?php

declare(strict_types=1);

namespace Drupal\{module}\Plugin\FieldWidgetAction;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\AiProviderPluginManager;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\field_widget_actions\Attribute\FieldWidgetAction;
use Drupal\field_widget_actions\FieldWidgetActionBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * {Description of what the action does}.
 */
#[FieldWidgetAction(
  id: '{plugin_id}',
  label: new TranslatableMarkup('{Plugin Label}'),
  widget_types: [
    'string_textfield',
    'text_textarea',
    'text_textarea_with_summary',
  ],
  field_types: [
    'string',
    'string_long',
    'text',
    'text_long',
    'text_with_summary',
  ],
  category: new TranslatableMarkup('{Category}'),
  multiple: TRUE,
  description: new TranslatableMarkup('{Description}'),
)]
class {ClassName} extends FieldWidgetActionBase {

  /**
   * The AI provider plugin manager.
   */
  protected AiProviderPluginManager $aiProvider;

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->aiProvider = $container->get('ai.provider');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function getAjaxCallback(): ?string {
    return 'ajaxCallback';
  }

  /**
   * AJAX callback that processes the action.
   *
   * @param array $form
   *   The form array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response.
   */
  public function ajaxCallback(array &$form, FormStateInterface $form_state): AjaxResponse {
    $selector = $this->getSuggestionsTarget($form, $form_state);

    // --- Core action logic here ---
    // Use $this->aiProvider to call AI, $this->getConfiguration() for settings.
    // Use $this->buildEntity($form, $form_state) for current entity context.

    $defaults = $this->aiProvider->getDefaultProviderForOperationType('chat');
    if (empty($defaults['provider_id']) || empty($defaults['model_id'])) {
      return $this->returnSuggestions([], $selector);
    }

    $provider = $this->aiProvider->createInstance($defaults['provider_id']);
    $input = new ChatInput([
      new ChatMessage('user', 'Suggest content for this field.'),
    ]);

    try {
      $response = $provider->chat($input, $defaults['model_id'], ['{module}']);
      $text = $response->getNormalized()->getText();
      return $this->returnSuggestions([$text], $selector);
    }
    catch (\Exception $e) {
      return $this->returnSuggestions([], $selector);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return parent::defaultConfiguration() + [
      'prompt' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(
    array $form,
    FormStateInterface $form_state,
    $action_id = NULL,
  ) {
    $element = parent::buildConfigurationForm($form, $form_state, $action_id);
    $configuration = $this->getConfiguration();

    $element['prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Prompt'),
      '#default_value' => $configuration['prompt'] ?? '',
      '#description' => $this->t('The prompt sent to the AI provider.'),
    ];

    return $element;
  }

}
```

### Pattern B: Modal form action (user interaction before processing)

For actions that need user input via a modal dialog before filling the
field. More complex — requires `FieldWidgetFormActionBase` and implements
`buildModalForm()` + `submitModalFormFillFields()`.

**Extends:** `FieldWidgetFormActionBase`

```php
<?php

declare(strict_types=1);

namespace Drupal\{module}\Plugin\FieldWidgetAction;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\field_widget_actions\Ajax\FillEditorCommand;
use Drupal\field_widget_actions\Ajax\FillSimpleFieldCommand;
use Drupal\field_widget_actions\Attribute\FieldWidgetAction;
use Drupal\field_widget_actions\FieldWidgetFormActionBase;

/**
 * {Description of what the action does}.
 */
#[FieldWidgetAction(
  id: '{plugin_id}',
  label: new TranslatableMarkup('{Plugin Label}'),
  widget_types: [
    'string_textfield',
    'text_textarea',
    'text_textarea_with_summary',
  ],
  field_types: [
    'string',
    'string_long',
    'text',
    'text_long',
    'text_with_summary',
  ],
  category: new TranslatableMarkup('{Category}'),
  multiple: TRUE,
)]
class {ClassName} extends FieldWidgetFormActionBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function buildModalForm(array $form, FormStateInterface $form_state, ContentEntityInterface|NULL $entity): array {
    // Add form elements for user choices.
    $form['option'] = [
      '#type' => 'select',
      '#title' => $this->t('Choose an option'),
      '#options' => [
        'option_a' => $this->t('Option A'),
        'option_b' => $this->t('Option B'),
      ],
      '#required' => TRUE,
    ];

    $form['custom_text'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Custom text'),
      '#description' => $this->t('Enter text to process.'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  protected function submitModalFormFillFields(array $form, FormStateInterface $form_state, AjaxResponse $response): AjaxResponse {
    $context_data = $form_state->get('field_widget_action_context_data');
    $target_element = $context_data['target_element'];
    $selector = '[name="' . $target_element['#name'] . '"]';

    // Build the value from form submission.
    $option = $form_state->getValue('option');
    $custom_text = $form_state->getValue('custom_text');
    $value = "[$option] $custom_text";

    // Use FillEditorCommand for formatted text fields (CKEditor),
    // FillSimpleFieldCommand for plain text fields.
    if (!empty($target_element['#format'])) {
      $response->addCommand(new FillEditorCommand($selector, $value));
    }
    else {
      $response->addCommand(new FillSimpleFieldCommand($selector, $value));
    }

    return $response;
  }

}
```

## Key Methods Reference

### FieldWidgetActionBase (AJAX pattern)

| Method | Purpose |
|--------|---------|
| `getAjaxCallback()` | Return the method name for the AJAX button callback. Return `NULL` to handle callbacks manually. |
| `completeFormAlter()` | Alter the complete field widget form (adds wrappers/buttons). |
| `singleElementFormAlter()` | Alter a single delta element (for `multiple = TRUE` plugins). |
| `getSuggestionsTarget()` | Get the CSS selector for the target form element. |
| `returnSuggestions()` | Show suggestions in a modal dialog with one-click insert. |
| `buildEntity()` | Build the entity from the current form state. |
| `isAvailable()` | Return `FALSE` to conditionally hide the action. |
| `getLibraries()` | Attach additional JS/CSS libraries to the widget. |
| `defaultConfiguration()` | Provide default config values. |
| `buildConfigurationForm()` | Build admin configuration form shown on form display settings. |

### FieldWidgetFormActionBase (modal form pattern)

| Method | Purpose |
|--------|---------|
| `buildModalForm()` | **Abstract.** Build the modal form fields. Receives current entity as context. |
| `submitModalFormFillFields()` | **Abstract.** Convert form submission to field fill commands (`FillSimpleFieldCommand`, `FillEditorCommand`). |
| `validateForm()` | Optional modal form validation. |
| `openModalCallback()` | Opens the modal dialog (handled by base class). |
| `getFormId()` | Returns form ID (auto-generated from plugin ID). |

### FORM_ELEMENT_PROPERTY

Override this constant to target the correct element property:

| Field type | Property |
|------------|----------|
| Most text fields | `value` (default) |
| Entity references | `target_id` |
| Image alt text | `alt` |
| Link fields | `uri` |

## Attribute Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `id` | `string` | Yes | Unique plugin ID. |
| `label` | `TranslatableMarkup` | Yes | Human-readable name shown in the UI. |
| `widget_types` | `array` | Yes | Widget types this action applies to (e.g. `string_textfield`). |
| `field_types` | `array` | Yes | Field types this action applies to (e.g. `string`, `text_long`). |
| `category` | `TranslatableMarkup` | No | Grouping label in the configuration UI. |
| `multiple` | `bool` | No | If `TRUE`, button shows per-delta (default `TRUE`). |
| `description` | `TranslatableMarkup` | No | Description of the plugin. |
| `deriver` | `class-string` | No | Deriver class for dynamic plugin definitions. |

## Step 3: Generate a Kernel/Functional Test

For AJAX-based actions, generate a test that verifies plugin discovery and
instantiation. For modal form actions, a FunctionalJavascript test may be
needed.

Create the test at `tests/src/Kernel/Plugin/FieldWidgetAction/{ClassName}Test.php`.

**Pattern:**

```php
<?php

declare(strict_types=1);

namespace Drupal\Tests\{module}\Kernel\Plugin\FieldWidgetAction;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the {ClassName} field widget action plugin.
 *
 * @group {module}
 * @covers \Drupal\{module}\Plugin\FieldWidgetAction\{ClassName}
 */
class {ClassName}Test extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field_widget_actions',
    '{module}',
    'system',
    'field',
    'text',
    'user',
  ];

  /**
   * Tests plugin discovery.
   */
  public function testPluginDiscovery(): void {
    $manager = $this->container->get('plugin.manager.field_widget_actions');
    $definitions = $manager->getDefinitions();
    $this->assertArrayHasKey('{plugin_id}', $definitions);
    $this->assertEquals('{Plugin Label}', (string) $definitions['{plugin_id}']['label']);
  }

  /**
   * Tests plugin instantiation.
   */
  public function testPluginInstantiation(): void {
    $manager = $this->container->get('plugin.manager.field_widget_actions');
    $instance = $manager->createInstance('{plugin_id}');
    $this->assertEquals('{plugin_id}', $instance->getPluginId());
  }

  /**
   * Tests default configuration.
   */
  public function testDefaultConfiguration(): void {
    $manager = $this->container->get('plugin.manager.field_widget_actions');
    $instance = $manager->createInstance('{plugin_id}');
    $config = $instance->defaultConfiguration();
    $this->assertArrayHasKey('enabled', $config);
    // Assert plugin-specific default config keys...
  }

}
```

## Recipe Configuration

Enable a field widget action via a Drupal recipe:

```yaml
config:
  actions:
    core.entity_form_display.node.page.default:
      setComponentThirdPartySetting:
        component: body
        provider: field_widget_actions
        settings:
          <uuid>:
            plugin_id: {plugin_id}
            enabled: true
            weight: 0
            button_label: '{Plugin Label}'
            settings:
              prompt: 'Suggest content for this field'
```

## Critical Rules

1. **Always extend the correct base class** — `FieldWidgetActionBase` for
   AJAX actions, `FieldWidgetFormActionBase` for modal form actions.
2. **Always implement `getAjaxCallback()`** for AJAX actions — return the
   method name as a string, or `NULL` to handle callbacks manually.
3. **Always implement `buildModalForm()` AND `submitModalFormFillFields()`**
   for modal form actions — both are abstract and required.
4. **Use `FillEditorCommand` for CKEditor fields** and
   `FillSimpleFieldCommand` for plain text fields. Check
   `$target_element['#format']` to determine which.
5. **Inject dependencies via `create()`** — never use
   `\Drupal::service()` in plugin classes.
6. **`widget_types` and `field_types` must be valid** — use machine names
   of actually installed widgets and field types.
7. **Run `drush cr` after creating or modifying a plugin** — Drupal caches
   plugin discovery.
8. **Enable the action via Form Display** — go to
   `/admin/structure/types/manage/{type}/form-display`, click the gear
   icon on a field, and enable the action in the "Field Widget Actions"
   third-party settings section.
9. **Plugin classes live in `src/Plugin/FieldWidgetAction/`** — discovery
   is automatic via the `#[FieldWidgetAction]` attribute.
10. **No routing or config files are needed** — the plugin system handles
    discovery and the form display UI handles configuration.

## Summary of Generated Files

| File | Purpose |
|------|---------|
| `src/Plugin/FieldWidgetAction/{ClassName}.php` | Plugin class |
| `tests/src/Kernel/Plugin/FieldWidgetAction/{ClassName}Test.php` | Kernel test for plugin discovery and instantiation |

No routing, services, or config YAML files are needed — plugin discovery is
automatic via the `#[FieldWidgetAction]` attribute and configuration is
managed through the Form Display UI.
