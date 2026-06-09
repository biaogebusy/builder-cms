## Function Call Plugins

> **Note:** Function Call plugins are available in version 1.2.x. In 2.0.x this API is expected to be replaced by the Tool API.

Function Call plugins allow an LLM to execute actions in your Drupal site. When an AI assistant needs to perform a task - like calculating a value, fetching data or modifying content - it can call a function that you define as a plugin.

### How it works

1. You define a plugin with a description and parameters
2. The LLM reads the description and decides when to use it
3. When called, the LLM passes the parameters to your plugin
4. Your plugin executes the logic and returns a result
5. The LLM uses the result to formulate a response to the user

### Creating a Function Call Plugin

A Function Call plugin is a PHP class placed in your module's `src/Plugin/AiFunctionCall/` directory.

It needs three things:

1. A `#[FunctionCall]` attribute that describes the plugin
2. Extending `FunctionCallBase`
3. Implementing `ExecutableFunctionCallInterface`

### Minimal example

Here is a simple example that greets a person by name:

```php
<?php

namespace Drupal\my_module\Plugin\AiFunctionCall;

use Drupal\ai\Attribute\FunctionCall;
use Drupal\ai\Base\FunctionCallBase;
use Drupal\ai\Service\FunctionCalling\ExecutableFunctionCallInterface;
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

#[FunctionCall(
  id: 'my_module:hello_world',
  function_name: 'hello_world',
  name: 'Hello World',
  description: 'Returns a greeting for a given name.',
  context_definitions: [
    'name' => new ContextDefinition(
      data_type: 'string',
      label: new TranslatableMarkup("Name"),
      description: new TranslatableMarkup("The name of the person to greet."),
      required: TRUE,
    ),
  ],
)]
class HelloWorld extends FunctionCallBase implements ExecutableFunctionCallInterface {

  /**
   * {@inheritdoc}
   */
  public function execute() {
    $name = $this->getContextValue('name');
    $this->setOutput("Hello, $name! Welcome to Drupal.");
  }

}
```

After creating the file, rebuild the cache so Drupal can discover the new plugin:

```bash
drush cr
```

The following sections explain each part of the plugin in detail.

### The FunctionCall attribute

The `#[FunctionCall]` attribute defines how the LLM sees your plugin.

| Parameter | Required | Description |
|-----------|----------|-------------|
| `id` | Yes | Unique plugin ID. The convention is to prefix with your module name, e.g. `my_module:hello_world`. |
| `function_name` | Yes | The function name the LLM will call. It may contain only alphanumeric characters and underscores. |
| `name` | Yes | Human-readable name. |
| `description` | Yes | Description of what the function does. The LLM reads this to decide when to use it, so make it clear and specific. |
| `group` | No | The function group this belongs to. Defaults to `other_fallback`. |
| `module_dependencies` | No | Array of module dependencies that must be enabled. |
| `context_definitions` | No | Array of parameters the LLM must provide. See below. |
| `deriver` | No | A deriver class for dynamic plugin generation. For advanced use cases only. |

### Defining parameters with context_definitions

Each parameter is a `ContextDefinition` that tells the LLM what input your function expects.

```php
context_definitions: [
  'city' => new ContextDefinition(
    data_type: 'string',
    label: new TranslatableMarkup("City"),
    description: new TranslatableMarkup("The city to look up (e.g. London)."),
    required: TRUE,
  ),
  'unit' => new ContextDefinition(
    data_type: 'string',
    label: new TranslatableMarkup("Unit"),
    description: new TranslatableMarkup("The unit to use (celsius or fahrenheit)."),
    required: FALSE,
    default_value: 'celsius',
  ),
],
```

Common `data_type` values are `string`, `integer`, `float` and `boolean`. Other Drupal Typed Data types may also work depending on the data type converters available.

You can add constraints to parameters to limit what the LLM can pass. See [Function Call Schema](function_call_schema.md) for details on constraints like `Choice`, `FixedValue`, `Length` and `Range`.

### The execute() method

This is where your logic goes. Use `$this->getContextValue('parameter_name')` to read the values provided by the LLM, and `$this->setOutput()` to set the text result that will be returned.

```php
public function execute() {
  $name = $this->getContextValue('name');
  // Your logic here...
  $this->setOutput("Hello, $name!");
}
```

### The getReadableOutput() method

This method returns a readable string representation of the result. The base class already implements this by returning whatever you passed to `setOutput()`. You only need to override it if you need custom formatting.

```php
public function getReadableOutput(): string {
  return "Custom formatted: " . $this->stringOutput;
}
```

### Returning structured output

If your function should also return structured data (e.g. for other modules to consume), implement `StructuredExecutableFunctionCallInterface` instead of `ExecutableFunctionCallInterface`. The `#[FunctionCall]` attribute stays the same - only the class definition changes:

```php
use Drupal\ai\Service\FunctionCalling\StructuredExecutableFunctionCallInterface;

class MyPlugin extends FunctionCallBase implements StructuredExecutableFunctionCallInterface {

  public function execute() {
    $city = $this->getContextValue('city');
    $this->setOutput("The weather in $city is 20°C.");
  }

  public function getStructuredOutput(): array {
    return [
      'city' => $this->getContextValue('city'),
      'temperature' => '20°C',
    ];
  }

  public function setStructuredOutput(array $output): void {
    $this->setOutput($output['temperature'] ?? '');
  }

}
```

`getStructuredOutput()` returns the result as an associative array. `setStructuredOutput()` is used to restore state from a previously stored structured output (e.g. when replaying a function call). You should restore any internal properties from `$output` so that `getReadableOutput()` and `getStructuredOutput()` return consistent values.

### Using dependency injection

If your plugin needs Drupal services, override the `create()` method. Make sure to pass the required base class dependencies:

```php
// Add to the use statements at the top of your file:
// use Drupal\ai\Service\FunctionCalling\FunctionCallInterface;
// use Symfony\Component\DependencyInjection\ContainerInterface;

public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): FunctionCallInterface|static {
  $instance = new static(
    $configuration,
    $plugin_id,
    $plugin_definition,
    $container->get('ai.context_definition_normalizer'),
    $container->get('plugin.manager.ai_data_type_converter'),
  );
  $instance->entityTypeManager = $container->get('entity_type.manager');
  return $instance;
}
```

### Function groups

Function Call plugins can be organized into groups using the `group` parameter in the attribute. Groups allow AI assistants to load only a relevant subset of tools. If you don't specify a group, the plugin is assigned to `other_fallback`.

Groups are defined as separate plugins using the `#[FunctionGroup]` attribute. The AI module provides several built-in groups like `information_tools` and `modification_tools`.

### Overriding context definitions per instance

The static `context_definitions` declared in the `#[FunctionCall]` attribute are shared by every instance of the plugin. Sometimes you need to adjust a parameter for a single call without affecting the underlying plugin definition - for example, to tighten a constraint, change a label, or add an additional parameter that only applies in a specific context.

`FunctionCallBase` implements `OverridableFunctionCallInterface`, which exposes a `setContextDefinitionOverride()` method for this purpose. Overrides apply only to the instance they are set on and take precedence over the definition declared in the attribute.

```php
use Drupal\Core\Plugin\Context\ContextDefinition;
use Drupal\Core\StringTranslation\TranslatableMarkup;

$instance = $function_call_manager->createInstance('my_module:hello_world');

$instance->setContextDefinitionOverride('name', new ContextDefinition(
  data_type: 'string',
  label: new TranslatableMarkup('Customer name'),
  description: new TranslatableMarkup('The customer to greet by name.'),
  required: TRUE,
));

// getContextDefinition() and getContextDefinitions() now return the
// overridden definition for this instance. Other instances are unaffected.
$definition = $instance->getContextDefinition('name');
```

Use this when you need to specialize a plugin for a specific caller. For anything that should apply globally, change the `#[FunctionCall]` attribute on the plugin instead.

### Related files

* [FunctionCallBase.php](https://git.drupalcode.org/project/ai/-/blob/1.2.x/src/Base/FunctionCallBase.php) - Base class with default implementations
* [ExecutableFunctionCallInterface.php](https://git.drupalcode.org/project/ai/-/blob/1.2.x/src/Service/FunctionCalling/ExecutableFunctionCallInterface.php) - Interface for executable function calls
* [StructuredExecutableFunctionCallInterface.php](https://git.drupalcode.org/project/ai/-/blob/1.2.x/src/Service/FunctionCalling/StructuredExecutableFunctionCallInterface.php) - Interface for structured output
* [OverridableFunctionCallInterface.php](https://git.drupalcode.org/project/ai/-/blob/1.2.x/src/Service/FunctionCalling/OverridableFunctionCallInterface.php) - Interface for per-instance context definition overrides
* [FunctionCall Schema](function_call_schema.md) - Documentation on parameter constraints

### Next steps

To learn how to add constraints to your function parameters (e.g. allowed values, length limits or numeric ranges), see [Function Call Schema](function_call_schema.md).
