---
name: create-automator-type
description: Scaffolds a new AiAutomatorType plugin for the Drupal AI Automators module. Recommends RuleBase (AI provider) or ExternalBase (deterministic) as base classes, with convenience subclasses (SimpleTextChat, ComplexTextChat, etc.) also available. Generates the plugin class with the correct base class, field_rule, and required methods.
---

# Create Automator Type Plugin (AI Automators Module)

This skill scaffolds a new automator type plugin for the Drupal AI Automators module. Automator types define how AI generates and stores values for Drupal fields - they run during automator execution and can generate text, structured values, media assets, or deterministic transformed output.

## Step 0: Determine Automator Strategy

**MANDATORY:** Before gathering any other requirements, ask the user to clarify which base class their automator needs. There are two fundamental base classes:

Ask the user:

> **Which base class does your automator need?**
>
> - **`RuleBase`** — for any automator that needs an AI provider processing step (chat, image generation, audio, structured JSON, etc.).
>   *Examples: `LlmSimpleString` (plain text), `LlmBoolean` (structured response), `LlmMediaAudioGeneration` (media).*
>
> - **`ExternalBase`** — for any automator that runs deterministic code without calling an AI provider.
>   *Example: `StripTags` (strips markup and stores cleaned text).*

Wait for the user's answer before proceeding.

### Convenience subclasses

`RuleBase` has several convenience subclasses that provide pre-built logic for common field types. If the automator matches one of these patterns, extending the subclass is preferred over raw `RuleBase`:

| Subclass | Use when |
|----------|----------|
| `SimpleTextChat` | Simple text generation stored as-is |
| `ComplexTextChat` | Structured JSON chat with parsing/validation |
| `Boolean` | Boolean field values |
| `NumericRule` | Numeric (integer/decimal/float) fields |
| `Taxonomy` | Taxonomy term references |
| `EntityReference` | Generic entity references |
| `Options` | List/options fields |
| `Email` | Email fields |
| `Link` | Link fields |
| `Telephone` | Telephone fields |
| `TextToImage` / `TextToMediaImage` | Image generation |
| `TextToSpeech` / `TextToMediaSpeech` | Audio/speech generation |
| `Summarize` | Text summarization |
| `CustomField` | Custom/compound field types |

All subclasses live in `modules/ai_automators/src/PluginBaseClasses/` and the Drush generator discovers them automatically.

## Step 1: Gather Requirements

Ask the user for:

1. **Plugin ID** (snake_case, e.g. `llm_simple_string`, `llm_boolean`, `strip_tags`)
2. **Plugin label** (human-readable, e.g. "LLM: Text (simple)")
3. **Field rule** - Drupal field type machine name (`string`, `text_long`, `boolean`, `entity_reference`, etc.)
4. **Target** - target entity type for reference/file automators (`media`, `file`, `taxonomy_term`, `any`) or `''` for scalar fields
5. **Base class** to extend — `RuleBase` for any automator that needs an AI provider processing step, `ExternalBase` for deterministic code, or a convenience subclass (see table in Step 0)
6. **Needs prompt?** - whether `needsPrompt()` should return `TRUE` or `FALSE`
7. **Allowed input field types** (`allowedInputs()` values)
8. **Storage strategy** - direct field set, formatted text values, entity/media creation, or custom mapping
9. **Validation requirements** - whether `verifyValue()` needs strict checks
10. **Extra configuration form fields** - custom settings via `extraFormFields()` / `extraAdvancedFormFields()`
11. **Dependency injection needs** - additional services beyond base class behavior

### For structured/media/custom automators, also ask:

12. **Response format expectations** - JSON schema, deterministic transform rules, or binary/media output shape
13. **Generation flow details** - whether custom `generate()` orchestration is required

## Step 2: Scaffold the Plugin with Drush

Use the Drush generator to scaffold the plugin base file. Run:

```bash
drush generate plugin:ai:automator-type
```

(Or via ddev: `ddev drush generate plugin:ai:automator-type`)

Alias is also valid:

```bash
drush generate ai-automator-type
```

The generator will ask:
1. **Module machine name** - the module where the plugin should be created
2. **Plugin label** - human-readable automator name
3. **Plugin ID** - machine name for the automator plugin
4. **Plugin class** - class name under `Plugin/AiAutomatorType`
5. **Field rule** - field type this automator applies to
6. **Target** - target entity type or empty string
7. **Base class** - `RuleBase` (AI provider) or `ExternalBase` (deterministic), plus all convenience subclasses discovered from `src/PluginBaseClasses/`
8. **Needs prompt?** - whether prompt UI/flow is required

This creates the plugin file at `src/Plugin/AiAutomatorType/{ClassName}.php` with the attribute, base class inheritance, and core structure in place.

### After scaffolding

Edit the generated file to implement:
1. The `generate()` logic for the selected automator type
2. Validation in `verifyValue()` if needed
3. Storage logic in `storeValues()` if default behavior is insufficient
4. Any additional form/config/token behavior needed for the automator

### Constructor dependency injection

**IMPORTANT:** Before adding a constructor for dependency injection, **read the base class** (`RuleBase` or `ExternalBase` in `modules/ai_automators/src/PluginBaseClasses/`) to check what constructor parameters it expects. `AiAutomatorTypeInterface` does **not** necessarily follow the standard Drupal `PluginBase` pattern — do not assume `$configuration, $plugin_id, $plugin_definition` are needed.

### What the generator produces

**Pattern A: `RuleBase` with prompt** — for automators that call an AI provider.
- The generated class extends `RuleBase` (or a convenience subclass like `ComplexTextChat`, `SimpleTextChat`, etc.) and includes `generate()`, `verifyValue()`, and `storeValues()` stubs.
- When extending a convenience subclass, most methods are already implemented — only override what differs.
- Uses `prepareLlmInstance()` and `runChatMessage()` helpers from `RuleBase`.

**Pattern B: `ExternalBase` (deterministic)** — for automators that transform data without an AI provider.
- The generated class extends `ExternalBase` with `generate()`, `verifyValue()`, and `storeValues()` stubs.
- Implement your deterministic logic directly in `generate()`.
- No AI provider setup needed.

**After scaffolding, implement the required methods:**
1. Implement `generate()` with the correct strategy
2. Implement `verifyValue()` for any strict validation requirements
3. Implement `storeValues()` when field storage requires custom mapping

## Automator Methods Reference

Use these methods from `Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface`:

| Method | When to use |
|--------|-------------|
| `needsPrompt()` | Return `TRUE` for LLM-driven automators, `FALSE` for deterministic/non-LLM automators. |
| `advancedMode()` | Enable advanced provider/model settings UI when needed. |
| `helpText()` | Explain behavior in the automator configuration UI. |
| `placeholderText()` | Provide default prompt text template. |
| `allowedInputs()` | Restrict valid source field types for context input. |
| `generate()` | Core generation method. Must return an array of values. |
| `verifyValue()` | Validate each generated value before storage. |
| `storeValues()` | Persist generated values to destination field/entity structures. |

### Common `RuleBase` helpers

`RuleBase` provides helpers such as `prepareLlmInstance()`, `runChatMessage()`, `runRawChatMessage()`, and response decoding helpers used by structured and custom automators.

## Step 3: Generate Kernel Test

Generate a kernel test that validates plugin discovery and basic instantiation behavior.

Create the test at `tests/src/Kernel/Plugin/AiAutomatorType/{ClassName}Test.php`.

**Pattern:**

```php
<?php

declare(strict_types=1);

namespace Drupal\Tests\{module}\Kernel\Plugin\AiAutomatorType;

use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the {ClassName} automator type plugin.
 *
 * @group {module}
 * @covers \Drupal\{module}\Plugin\AiAutomatorType\{ClassName}
 */
class {ClassName}Test extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai',
    'ai_automators',
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
    $manager = \Drupal::service('plugin.manager.ai_automator_type');
    $definitions = $manager->getDefinitions();
    $this->assertArrayHasKey('{plugin_id}', $definitions);
    $this->assertEquals('{Plugin Label}', (string) $definitions['{plugin_id}']['label']);
    $this->assertEquals('{field_rule}', $definitions['{plugin_id}']['field_rule']);
  }

  /**
   * Tests plugin instantiation.
   */
  public function testPluginInstantiation(): void {
    $manager = \Drupal::service('plugin.manager.ai_automator_type');
    $instance = $manager->createInstance('{plugin_id}');
    $this->assertInstanceOf(\Drupal\ai_automators\PluginInterfaces\AiAutomatorTypeInterface::class, $instance);
  }

}
```

**Critical rules for kernel tests:**
- Must enable `ai`, `ai_automators`, and the target module in `protected static $modules`.
- Must use `plugin.manager.ai_automator_type` for plugin discovery/instantiation.
- Keep kernel test scope focused on discovery, creation, and contract behavior.

## Critical Rules

1. **Always extend the correct base class** - `RuleBase` for any automator that needs an AI provider processing step, `ExternalBase` for any automator that is deterministic code.
2. **Always implement `AiAutomatorTypeInterface`** - every automator plugin must satisfy the interface contract.
3. **`field_rule` must match a real Drupal field type** - this controls automator applicability in field UI.
4. **`id` must follow the plugin naming pattern** - use stable snake_case and module conventions.
5. **`generate()` must return an array** - even for single generated values.
6. **`verifyValue()` validates one value at a time** - return `FALSE` to reject invalid values.
7. **`storeValues()` handles the full generated value array** - customize when default field assignment is insufficient.
8. **Set `$title` to match the plugin label** - keep UI naming consistent.
9. **Start from the Drush generator** - use `drush generate plugin:ai:automator-type` (or alias) before custom coding.
10. **Run `drush cr` after creating or changing plugins** - plugin discovery is cached.
11. **No routing needed** - automator plugin discovery is attribute-based via `#[AiAutomatorType]`.

## Reference Examples

- `modules/ai_automators/src/Drush/Generators/AiAutomatorTypeGenerator.php` - Drush generator class
- `modules/ai_automators/templates/Plugin/_ai-automator-type/ai-automator-type.twig` - plugin scaffold template
- `modules/ai_automators/src/PluginInterfaces/AiAutomatorTypeInterface.php` - core automator interface
- `modules/ai_automators/src/Attribute/AiAutomatorType.php` - plugin attribute definition
- `modules/ai_automators/src/PluginManager/AiAutomatorTypeManager.php` - plugin manager
- `modules/ai_automators/src/PluginBaseClasses/RuleBase.php` - base class for AI provider-driven automators
- `modules/ai_automators/src/PluginBaseClasses/ExternalBase.php` - base class for deterministic automators
- `modules/ai_automators/src/Plugin/AiAutomatorType/LlmSimpleString.php` - simple text example (extends RuleBase via SimpleTextChat)
- `modules/ai_automators/src/Plugin/AiAutomatorType/LlmBoolean.php` - structured value example (extends RuleBase)
- `modules/ai_automators/src/Examples/AiAutomatorType/StripTags.php.example` - deterministic example (extends ExternalBase)

## Summary of Generated Files

| File | Purpose |
|------|---------|
| `src/Plugin/AiAutomatorType/{ClassName}.php` | Automator plugin class (scaffolded by `drush generate plugin:ai:automator-type`) |
| `src/PluginBaseClasses/{BaseName}.php` | *(Optional)* reusable base class when shared logic is required |
| `tests/src/Kernel/Plugin/AiAutomatorType/{ClassName}Test.php` | Kernel test for plugin discovery and instantiation |

No routing, services, or config schema updates are required by default for automator plugin creation - discovery is automatic via the `#[AiAutomatorType]` attribute.
