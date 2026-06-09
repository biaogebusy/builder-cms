# Tools Library Form Element

The `ai_tools_library` form element provides an AI tools picker with a modal interface. It lets users browse and select which Function Call plugins (AI tools) should be available for a given AI interaction, such as configuring an agent or assistant.

## Usage

Add the element to any Drupal form:

```php
$form['tools'] = [
  '#type' => 'ai_tools_library',
  '#title' => $this->t('Tools for this agent'),
  '#description' => $this->t('Choose the tools you want to use with this agent.'),
  '#default_value' => '',
];
```

When the user clicks the "Select tools" button, a modal dialog opens showing all available Function Call plugins organized by group. Selected tools appear as labeled items below the button.

## Properties

- **`#title`** (string): The label for the form element.
- **`#description`** (string, optional): Help text displayed with the element.
- **`#default_value`** (string|array): Pre-selected tool plugin IDs. Accepts:
    - A comma-separated string: `'ai_tool_1,ai_tool_2,ai_tool_3'`
    - An indexed array: `['ai_tool_1', 'ai_tool_2']`
    - An associative array (keys are used as IDs): `['ai_tool_1' => TRUE, 'ai_tool_2' => TRUE]`

## Return value

On form submission, the element returns an array of selected tool plugin IDs:

```php
// In your submitForm() method:
$tools = $form_state->getValue('tools');
// $tools is an array, e.g. ['ai_tool_1', 'ai_tool_2']
```

## How the modal works

The element renders:

1. A hidden input that stores the selected tool IDs as a comma-separated string.
2. A list of currently selected tools, each showing the plugin's name and description.
3. A "Select tools" button that opens a modal dialog via AJAX.

The modal displays all registered Function Call plugins (from the `plugin.manager.ai.function_calls` service), organized by Function Group (from `plugin.manager.ai.function_groups`). Users can browse groups using tabs and toggle individual tools on or off.

When the user confirms their selection in the modal, the hidden input is updated and the widget refreshes via AJAX to show the new selection.

## Examples

### Basic usage with no default selection

```php
$form['tools'] = [
  '#type' => 'ai_tools_library',
  '#title' => $this->t('AI Tools'),
  '#description' => $this->t('Select the tools this assistant can use.'),
  '#default_value' => '',
];
```

### Pre-selecting tools

```php
$form['tools'] = [
  '#type' => 'ai_tools_library',
  '#title' => $this->t('Tools for this agent'),
  '#default_value' => 'weather_lookup,content_search,calculator',
];
```

### Using a saved configuration value

```php
$config = $this->config('my_module.settings');

$form['tools'] = [
  '#type' => 'ai_tools_library',
  '#title' => $this->t('Available Tools'),
  '#default_value' => $config->get('enabled_tools') ?? '',
];
```

### Saving the submitted value

```php
public function submitForm(array &$form, FormStateInterface $form_state): void {
  $tools = $form_state->getValue('tools');

  // Save as a comma-separated string.
  $this->config('my_module.settings')
    ->set('enabled_tools', implode(',', $tools))
    ->save();
}
```

## Related documentation

- [Function Call Plugins](function_call.md) - How to create the tools that appear in the library.
- [Function Call Schema](function_call_schema.md) - Defining parameters for function call plugins.
