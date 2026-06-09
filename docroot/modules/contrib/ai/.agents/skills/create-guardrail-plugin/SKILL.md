---
name: create-guardrail-plugin
description: Scaffolds a new AiGuardrail plugin for the Drupal AI module. Asks whether the guardrail is deterministic or non-deterministic, then generates the plugin class with configuration form, processInput/processOutput methods, and appropriate result types.
---

# Create Guardrail Plugin (AI Module)

This skill scaffolds a new guardrail plugin for the Drupal AI module. Guardrails add safety and validation checks on AI inputs and outputs - they run automatically before and/or after AI generation and can pass, stop, or rewrite content.

## Step 0: Determine Guardrail Type

**MANDATORY:** Before gathering any other requirements, ask the user to clarify which type of guardrail they need. This determines the class hierarchy and interfaces.

### Deterministic vs Non-Deterministic

Ask the user:

> **Is this guardrail deterministic or non-deterministic?**
>
> - **Deterministic** guardrails use fixed rules to validate input/output - things like regular expressions, word counts, blocklists, length checks, format validation. They always produce the same result for the same input. They are fast, predictable, and don't require external services.
>   *Example: `RegexpGuardrail` - checks text against a regex pattern.*
>
> - **Non-deterministic** guardrails use AI to make their decisions - things like topic classification, sentiment analysis, toxicity detection, or semantic similarity checks. They call an AI provider internally, so results may vary between runs. They require an AI provider/model to be configured.
>   *Example: `RestrictToTopic` - uses an LLM to classify whether text matches allowed/disallowed topics.*
>
> Which type does your guardrail need?

Wait for the user's answer before proceeding.

## Step 1: Gather Requirements

Ask the user for:

1. **Plugin ID** (snake_case, e.g. `max_word_count`, `restrict_to_topic`, `blocklist_filter`)
2. **Plugin label** (human-readable, e.g. "Max Word Count", "Restrict to Topic")
3. **Plugin description** (e.g. "Blocks input that exceeds a word limit.")
4. **What does the guardrail check?** - The core validation logic
5. **Does it process input, output, or both?** - Most guardrails only process input (pre-generation). Some also check output (post-generation).
6. **What result types should it return?** - `PassResult`, `StopResult`, `RewriteInputResult`, `RewriteOutputResult`
7. **Does it need a configuration form?** - Admin-configurable settings like thresholds, patterns, messages
8. **Can it handle streamed responses?** - If it processes output, does it need the full response text? If so, it needs `NonStreamableGuardrailInterface`.

### For non-deterministic guardrails, also ask:

9. **What AI operation does it use internally?** (usually `chat`)
10. **What prompt does it send to the AI?** - The classification/analysis prompt

## Step 2: Scaffold the Plugin with Drush

Use the Drush generator to scaffold the plugin base file. Run:

```bash
drush generate plugin:ai:guardrail
```

(Or via ddev: `ddev drush generate plugin:ai:guardrail`)

The generator will ask:
1. **Module machine name** - the module to place the plugin in (e.g. `ai`, or a custom module)
2. **Guardrail label** - human-readable name (e.g. "Blocklist Filter")
3. **Plugin ID** - auto-derived as snake_case from the label, confirm or override
4. **Description** - brief description of what it does
5. **Non-deterministic?** - whether the guardrail calls AI internally (the generator explains the difference)

This creates the plugin file at `src/Plugin/AiGuardrail/{ClassName}.php` with the correct base class, interfaces, and method stubs. The generated file contains `@todo` markers where the custom logic needs to be implemented.

### After scaffolding

Edit the generated file to implement:
1. The `processInput()` validation logic (replace the `@todo` section)
2. The `processOutput()` logic if the guardrail also checks output
3. Additional configuration form fields in `buildConfigurationForm()`

### What the generator produces

**Pattern A (deterministic)** - extends `AiGuardrailPluginBase`, implements `ConfigurableInterface`, `PluginFormInterface`:
- `processInput()` with ChatInput type check, last message extraction, and `@todo` for validation logic
- `processOutput()` returning PassResult (no-op)
- Full `ConfigurableInterface` boilerplate (get/set/default configuration)
- `buildConfigurationForm()` with a violation message field and `@todo` for additional fields
- Template: `templates/guardrail/deterministic.twig`

**Pattern B (non-deterministic)** - additionally implements `NonDeterministicGuardrailInterface`, `NonStreamableGuardrailInterface`, `ContainerFactoryPluginInterface`:
- Everything from Pattern A, plus:
- `NeedsAiPluginManagerTrait` for AI provider access
- `create()` method injecting `AiProviderFormHelper`
- `processInput()` with AI provider instantiation, prompt building, and `@todo` for response parsing
- `buildConfigurationForm()` with `AiProviderFormHelper::generateAiProvidersForm()` for LLM provider/model selection
- `submitConfigurationForm()` with proper LLM config extraction and type casting
- Template: `templates/guardrail/non_deterministic.twig`

**After scaffolding, implement the `@todo` sections:**
1. Add your validation logic in `processInput()` - use `$text` (the last message) and `$this->configuration` for settings
2. Add configuration form fields in `buildConfigurationForm()` for any guardrail-specific settings
3. For non-deterministic: build the AI classification prompt and parse the response

## Result Types Reference

Use these result types from `Drupal\ai\Guardrail\Result\`:

| Result | When to use | `stop()` |
|--------|-------------|----------|
| `PassResult($message, $this)` | Input/output is acceptable | `false` |
| `StopResult($message, $this, $context, $score)` | Input/output should be blocked. `$score` (default `1.0`) is aggregated across guardrails in a set. | `true` |
| `RewriteInputResult($message, $this, $context)` | Rewrite the last chat message text with `$message` (pre-generation only). | `false` |
| `RewriteOutputResult($message, $this, $context)` | Rewrite the AI response text with `$message` (post-generation only). | `false` |

### Score aggregation

Each guardrail set has a **stop threshold** (float). `StopResult` scores are summed across all guardrails in the set. If the total reaches the threshold, the AI call is skipped (pre) or the output is replaced (post). Use partial scores (e.g. `0.3`, `0.5`) when you want multiple guardrails to contribute to a combined decision.

## Step 3: Add Config Schema

If the guardrail has a configuration form, add a config schema entry to `config/schema/ai.schema.yml` so Drupal's strict config validation passes.

The base guardrail entity schema at `ai.ai_guardrail.*` references `guardrail_settings` with the dynamic type `ai.guardrail.settings.[%parent.guardrail]`. A fallback `ai.guardrail.settings.*` exists as an empty mapping for plugins without specific schemas.

**Add the per-plugin schema:**

```yaml
ai.guardrail.settings.{plugin_id}:
  type: mapping
  label: {Plugin Label} settings
  mapping:
    {config_key_1}:
      type: {string|integer|boolean|float}
      label: {Human-readable label}
    {config_key_2}:
      type: {string|integer|boolean|float}
      label: {Human-readable label}
    # ... one entry per configuration field
```

**For non-deterministic guardrails**, also add the LLM provider configuration keys:

```yaml
ai.guardrail.settings.{plugin_id}:
  type: mapping
  label: {Plugin Label} settings
  mapping:
    # ... plugin-specific fields ...
    llm_provider:
      type: string
      label: LLM provider
    llm_model:
      type: string
      label: LLM model
    llm_config:
      type: mapping
      label: LLM configuration
```

**Critical rules for schema:**
- Each config key's type must match the form element type: `#type => 'number'` -> `integer`, `#type => 'checkbox'` -> `boolean`, `#type => 'textfield'`/`#type => 'textarea'` -> `string`.
- The schema file is at `config/schema/ai.schema.yml` in the AI module root.

**Reference schemas:**
- `ai.guardrail.settings.input_length_limit` - deterministic with integer, boolean, string fields
- `ai.guardrail.settings.regexp_guardrail` - deterministic with string fields
- `ai.guardrail.settings.restrict_to_topic` - non-deterministic with LLM provider fields

## Step 4: Generate Kernel Test (deterministic guardrails)

For deterministic guardrails, generate a kernel test that validates the guardrail works end-to-end with the EchoAI provider, guardrail entities, and guardrail sets.

Create the test at `tests/src/Kernel/Plugin/AiGuardrail/{ClassName}KernelTest.php`.

**Pattern:**

```php
<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Kernel\Plugin\AiGuardrail;

use Drupal\ai\Entity\AiGuardrail;
use Drupal\ai\Entity\AiGuardrailSet;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ChatOutput;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the {ClassName} guardrail in a full Drupal kernel context.
 *
 * @group ai
 * @covers \Drupal\ai\Plugin\AiGuardrail\{ClassName}
 */
class {ClassName}KernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai',
    'ai_test',
    'key',
    'file',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('ai_mock_provider_result');

    // Create a guardrail entity wrapping the plugin.
    $guardrail = AiGuardrail::create([
      'id' => 'test_{plugin_id}',
      'label' => 'Test {Plugin Label}',
      'description' => 'Test guardrail.',
      'guardrail' => '{plugin_id}',
      'guardrail_settings' => [
        // Plugin configuration for the test...
      ],
    ]);
    $guardrail->save();

    // Create a guardrail set with the guardrail as a pre-generate guardrail.
    $guardrail_set = AiGuardrailSet::create([
      'id' => 'test_{plugin_id}_set',
      'label' => 'Test Set',
      'description' => 'Test guardrail set.',
      'stop_threshold' => 1.0,
      'pre_generate_guardrails' => ['plugin_id' => ['test_{plugin_id}']],
      'post_generate_guardrails' => ['plugin_id' => []],
    ]);
    $guardrail_set->save();
  }

  /**
   * Test that input passing the guardrail reaches the AI provider.
   */
  public function testPassingInput(): void {
    $provider = \Drupal::service('ai.provider')->createInstance('echoai');
    $input = new ChatInput([
      new ChatMessage('user', '{short/valid input}'),
    ]);

    $guardrail_helper = \Drupal::service('ai.guardrail_helper');
    $input = $guardrail_helper->applyGuardrailSetToChatInput('test_{plugin_id}_set', $input);

    $result = $provider->chat($input, 'gpt-test', ['test']);
    $this->assertInstanceOf(ChatOutput::class, $result);
    // EchoAI echoes the input - verify the original text is in the response.
    $this->assertStringContainsString('{short/valid input}', $result->getNormalized()->getText());
  }

  /**
   * Test that input failing the guardrail is blocked.
   */
  public function testBlockedInput(): void {
    $provider = \Drupal::service('ai.provider')->createInstance('echoai');
    $input = new ChatInput([
      new ChatMessage('user', '{long/invalid input}'),
    ]);

    $guardrail_helper = \Drupal::service('ai.guardrail_helper');
    $input = $guardrail_helper->applyGuardrailSetToChatInput('test_{plugin_id}_set', $input);

    $result = $provider->chat($input, 'gpt-test', ['test']);
    $this->assertInstanceOf(ChatOutput::class, $result);
    // The guardrail should have forced a stop message as the output.
    $this->assertStringContainsString('{expected violation text}', $result->getNormalized()->getText());
    // Verify guardrail results were recorded.
    $this->assertNotEmpty($input->getGuardrailsResults());
  }

  /**
   * Test the plugin can be loaded via the plugin manager.
   */
  public function testPluginDiscovery(): void {
    $plugin_manager = \Drupal::service('plugin.manager.ai_guardrail');
    $plugin = $plugin_manager->createInstance('{plugin_id}', [/* config */]);
    $this->assertEquals('{Plugin Label}', $plugin->label());
    $this->assertTrue($plugin->isAvailable());
  }

}
```

**Critical rules for kernel tests:**
- Step 3 (config schema) MUST be completed before kernel tests can pass with strict schema checking.
- MUST use `'pre_generate_guardrails' => ['plugin_id' => ['{guardrail_entity_id}']]` format - the `AiGuardrailSet` entity expects this nested structure.
- MUST use `'post_generate_guardrails' => ['plugin_id' => []]` for empty post-generate lists.
- Use `\Drupal::service('ai.guardrail_helper')` to apply the guardrail set to input.
- Use `\Drupal::service('plugin.manager.ai_guardrail')` (not `ai.guardrail`) for the plugin manager.
- The EchoAI provider echoes input as `"Hello world! Input: {text}. Config: []."` for passing calls.
- When a guardrail stops execution, the forced output contains the violation message from the `StopResult`.

## Critical Rules

1. **Always extend `AiGuardrailPluginBase`** - provides `label()` and `isAvailable()`.
2. **Always implement both `processInput()` and `processOutput()`** - even if one just returns `PassResult`.
3. **Check input type first** - return `PassResult` early if the input is not `ChatInput` (or whatever type your guardrail supports).
4. **Get the last message** from `$input->getMessages()` using `end($messages)` - guardrails typically check the most recent user message.
5. **Non-deterministic guardrails MUST implement** `NonDeterministicGuardrailInterface` and use `NeedsAiPluginManagerTrait` - the plugin manager automatically injects `AiProviderPluginManager`.
6. **Non-deterministic guardrails SHOULD implement** `NonStreamableGuardrailInterface` - they typically need the full response text.
7. **Non-deterministic guardrails MUST implement** `ContainerFactoryPluginInterface` with a `create()` method to inject `AiProviderFormHelper`.
8. **Configuration form** - use `ConfigurableInterface` + `PluginFormInterface` for admin-configurable settings.
9. **Config schema** - add a `ai.guardrail.settings.{plugin_id}` entry to `config/schema/ai.schema.yml` for each configurable guardrail. This is required for Drupal's strict config schema validation.
10. **Plugin discovery** - plugins live in `src/Plugin/AiGuardrail/` and use the `#[AiGuardrail]` attribute.
11. **No routing needed** - guardrails are managed through the admin UI at `/admin/config/ai/guardrails`. The guardrail entity system handles the rest.

## Reference Examples

- `src/Drush/Generators/GuardrailGenerator.php` - The Drush generator class
- `templates/guardrail/deterministic.twig` - Twig template for deterministic guardrails
- `templates/guardrail/non_deterministic.twig` - Twig template for non-deterministic guardrails
- `src/Plugin/AiGuardrail/RegexpGuardrail.php` - **Deterministic**: simple regex check with configuration form
- `src/Plugin/AiGuardrail/InputLengthLimit.php` - **Deterministic**: length limit with character/token modes, DI for tokenizer
- `src/Plugin/AiGuardrail/RestrictToTopic.php` - **Non-deterministic**: AI-powered topic classification with provider/model configuration
- `src/Guardrail/AiGuardrailPluginBase.php` - Base class
- `src/Guardrail/AiGuardrailInterface.php` - Core interface
- `src/Guardrail/Result/` - All result types
- `src/Guardrail/NeedsAiPluginManagerTrait.php` - Trait for AI provider injection
- `config/schema/ai.schema.yml` - Schema definitions for guardrail settings

## Summary of Generated Files

| File | Purpose |
|------|---------|
| `src/Plugin/AiGuardrail/{ClassName}.php` | Guardrail plugin class (scaffolded by `drush generate plugin:ai:guardrail`) |
| `config/schema/ai.schema.yml` | *(Updated)* Config schema for guardrail settings |
| `tests/src/Unit/Plugin/AiGuardrail/{ClassName}Test.php` | Unit tests for the plugin logic |
| `tests/src/Kernel/Plugin/AiGuardrail/{ClassName}KernelTest.php` | *(Deterministic only)* Kernel test with EchoAI provider and guardrail entities |

No routing or entity config files are needed - guardrail entities and sets are configured through the admin UI, and plugin discovery is automatic via the `#[AiGuardrail]` attribute.
