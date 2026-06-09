---
name: create-operation-type
description: Scaffolds a new AI module operation type with interface, input, output, optional item class, and unit tests. Validates all generated files with a PHP verification script before completing.
---

# Create Operation Type (AI Module)

This skill scaffolds a complete new operation type for the Drupal AI module. It generates all required PHP files under `src/OperationType/{OperationName}/` and corresponding unit tests, then validates everything with a verification script.

## Step 0: Gather Requirements

Ask the user for:

1. **Operation name** (PascalCase, e.g. `TextToImage`, `ImageClassification`, `SpeechToText`)
2. **Human-readable label** (e.g. "Text To Image", "Image Classification")
3. **Primary method name** (camelCase, e.g. `textToImage`, `imageClassification`) - this is the main method on the interface that providers implement
4. **Primary method input type hint** - what types the method accepts besides the Input class (e.g. `string`, `string|array`). Default: `string`
5. **Input class fields** - what data the input holds (e.g. `text: string`, `file: ImageFile, labels: string[]`)
6. **Output normalized type** - what the normalized output holds (e.g. `string`, `array of ImageFile`, `ModerationResponse object`)
7. **Does the output need an Item/Response class?** - If the normalized output contains complex objects (not simple scalars or existing GenericType files), a separate data class is needed (like `ImageClassificationItem` or `ModerationResponse`). Ask what fields the item class should have.
8. **Any additional interface methods?** (e.g. `getMaxInputTokens(string $model_id): int`)

### Derive these automatically:

- **Operation type ID**: Convert PascalCase to snake_case (e.g. `TextToImage` -> `text_to_image`, `ImageClassification` -> `image_classification`). MUST use underscores, never dashes.
- **Directory**: `src/OperationType/{OperationName}/`
- **Test directory**: `tests/src/Unit/OperationType/{OperationName}/`

## Step 1: Generate the Interface

Create `src/OperationType/{OperationName}/{OperationName}Interface.php`.

**Pattern** (follow exactly):

```php
<?php

namespace Drupal\ai\OperationType\{OperationName};

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\OperationType;
use Drupal\ai\OperationType\OperationTypeInterface;

/**
 * Interface for {human readable label} models.
 */
#[OperationType(
  id: '{operation_type_id}',
  label: new TranslatableMarkup('{Human Readable Label}'),
)]
interface {OperationName}Interface extends OperationTypeInterface {

  /**
   * {Method description}.
   *
   * @param {input_type_hint}|\Drupal\ai\OperationType\{OperationName}\{OperationName}Input $input
   *   The {operation} input.
   * @param string $model_id
   *   The model id to use.
   * @param array $tags
   *   Extra tags to set.
   *
   * @return \Drupal\ai\OperationType\{OperationName}\{OperationName}Output
   *   The {operation} output.
   */
  public function {methodName}({input_type_hint}|{OperationName}Input $input, string $model_id, array $tags = []): {OperationName}Output;

}
```

**Critical rules:**
- The `id` in `#[OperationType]` MUST be snake_case using only `[a-z0-9_]` characters. Never use dashes.
- The `label` MUST use `new TranslatableMarkup('...')`.
- The interface MUST extend `OperationTypeInterface`.
- The primary method MUST accept the Input class and return the Output class.
- Import order: `TranslatableMarkup`, then `OperationType` attribute, then `OperationTypeInterface`.

**Reference examples:**
- `src/OperationType/TextToImage/TextToImageInterface.php` - simple interface with one method
- `src/OperationType/ImageClassification/ImageClassificationInterface.php` - interface with one method
- `src/OperationType/Chat/ChatInterface.php` - interface with additional helper methods

## Step 2: Generate the Input Class

Create `src/OperationType/{OperationName}/{OperationName}Input.php`.

**Pattern** (follow exactly):

```php
<?php

namespace Drupal\ai\OperationType\{OperationName};

use Drupal\ai\OperationType\InputBase;
use Drupal\ai\OperationType\InputInterface;

/**
 * Input object for {human readable label} input.
 */
class {OperationName}Input extends InputBase implements InputInterface {

  /**
   * {Field description}.
   *
   * @var {type}
   */
  private {type} ${fieldName};

  /**
   * The constructor.
   *
   * @param {type} ${field_name}
   *   {Field description}.
   */
  public function __construct({type} ${field_name}) {
    $this->{fieldName} = ${field_name};
  }

  // Getter and setter for each field...

  /**
   * {@inheritdoc}
   */
  public function toString(): string {
    // Return a string representation of the primary input.
    return $this->{primaryField};
  }

  /**
   * Return the input as string.
   *
   * @return string
   *   The input as string.
   */
  public function __toString(): string {
    return $this->toString();
  }

  /**
   * {@inheritdoc}
   */
  public function toArray(): array {
    return [
      '{field_name}' => $this->{fieldName},
      // Include all fields...
    ];
  }

}
```

**Critical rules:**
- MUST extend `InputBase` and implement `InputInterface`.
- MUST implement `toString(): string`, `__toString(): string`, and `toArray(): array`.
- Each field needs a getter and setter with proper PHPDoc.
- Constructor assigns all required fields.

**Reference examples:**
- `src/OperationType/TextToImage/TextToImageInput.php` - simple single-field input
- `src/OperationType/Moderation/ModerationInput.php` - simple single-field input
- `src/OperationType/ImageClassification/ImageClassificationInput.php` - multi-field input with ImageFile
- `src/OperationType/Embeddings/EmbeddingsInput.php` - input with optional fields

## Step 3: Generate the Output Class

Create `src/OperationType/{OperationName}/{OperationName}Output.php`.

**Pattern** (follow exactly):

```php
<?php

namespace Drupal\ai\OperationType\{OperationName};

use Drupal\ai\OperationType\OutputInterface;

/**
 * Data transfer output object for {human readable label} output.
 */
class {OperationName}Output implements OutputInterface {

  /**
   * {Description of normalized output}.
   *
   * @var {normalized_type}
   */
  private {normalized_type} $normalized;

  /**
   * The raw output from the AI provider.
   *
   * @var mixed
   */
  private mixed $rawOutput;

  /**
   * The metadata from the AI provider.
   *
   * @var mixed
   */
  private mixed $metadata;

  /**
   * The constructor.
   *
   * @param {normalized_type} $normalized
   *   The normalized output.
   * @param mixed $rawOutput
   *   The raw output from the AI provider.
   * @param mixed $metadata
   *   The metadata from the AI provider.
   */
  public function __construct({normalized_type} $normalized, mixed $rawOutput, mixed $metadata) {
    $this->normalized = $normalized;
    $this->rawOutput = $rawOutput;
    $this->metadata = $metadata;
  }

  /**
   * Returns the normalized output.
   *
   * @return {normalized_type}
   *   The normalized output.
   */
  public function getNormalized(): {normalized_type} {
    return $this->normalized;
  }

  /**
   * Gets the raw output from the AI provider.
   *
   * @return mixed
   *   The raw output.
   */
  public function getRawOutput(): mixed {
    return $this->rawOutput;
  }

  /**
   * Gets the metadata from the AI provider.
   *
   * @return mixed
   *   The metadata.
   */
  public function getMetadata(): mixed {
    return $this->metadata;
  }

  /**
   * {@inheritdoc}
   */
  public function toArray(): array {
    return [
      'normalized' => $this->normalized,
      'rawOutput' => $this->rawOutput,
      'metadata' => $this->metadata,
    ];
  }

}
```

**Critical rules:**
- MUST implement `OutputInterface`.
- MUST have `$normalized`, `$rawOutput`, and `$metadata` properties.
- MUST implement `getNormalized()`, `getRawOutput()`, `getMetadata()`, and `toArray()`.
- `OutputInterface::getNormalized()` is **intentionally declared without a return type** so each concrete Output class can narrow the return type to whatever its operation produces (e.g. `array`, `string`, a specific Response object). Adding `: {normalized_type}` on the concrete class is correct - it's a deliberate narrowing, not a violation of the interface. Do not "fix" the interface to add a type.
- `toArray()` **must recursively serialize object values** inside `$normalized` so the result is a plain array of scalars/arrays. If `$normalized` is a single object with its own `toArray()`, call it. If it is an array of objects, map over them:
  ```php
  public function toArray(): array {
    return [
      'normalized' => array_map(
        fn($item) => $item instanceof {OperationName}Item ? $item->toArray() : $item,
        $this->normalized,
      ),
      'rawOutput' => $this->rawOutput,
      'metadata' => $this->metadata,
    ];
  }
  ```
  Returning `$this->normalized` as-is when it contains objects (as a few older outputs do, e.g. `TextClassificationOutput`) is a bug - copy the pattern from `TextToImageOutput`, not from outputs that return objects unserialized.

**Reference examples:**
- `src/OperationType/TextToImage/TextToImageOutput.php` - output with array of ImageFile objects (serializes in toArray)
- `src/OperationType/Moderation/ModerationOutput.php` - output with single complex ModerationResponse object
- `src/OperationType/ImageClassification/ImageClassificationOutput.php` - output with array of ImageClassificationItem objects

## Step 4: Generate Item/Response Class (if needed)

**When to generate:** If the output's normalized data contains complex objects that don't already exist in the codebase (not ImageFile, AudioFile, etc.), create a separate data class.

Examples of when an Item class IS needed:
- `ImageClassificationItem` - holds label + confidence score per classification
- `ModerationResponse` - holds flagged boolean + information array
- An Item for extractive question answering might hold `answer`, `score`, `start`, `end`
- Any case where the normalized output contains structured data beyond simple scalars or existing GenericType files

**Do NOT anchor the shape to classification.** The label/confidence-score pattern is specific to classification operations. Ask the user what fields the Item actually needs for their operation type and build the class from that - do not default to `label` + `confidenceScore` or copy `getConfidenceScorePercentage()` unless the user explicitly confirms the operation is classification-shaped. That is just an example for one common type of Item, not a template for all Items.

Create `src/OperationType/{OperationName}/{OperationName}Item.php` (or `{OperationName}Response.php` if more appropriate).

**Generic pattern:**

```php
<?php

namespace Drupal\ai\OperationType\{OperationName};

/**
 * {Description of what this item represents}.
 */
class {OperationName}Item {

  /**
   * {Description of field_1}.
   *
   * @var {type_1}
   */
  private {type_1} ${field_1};

  /**
   * {Description of field_2}.
   *
   * @var {type_2}
   */
  private {type_2} ${field_2};

  // ... one property per field the user specified.

  /**
   * The constructor.
   *
   * @param {type_1} ${field_1}
   *   {Description of field_1}.
   * @param {type_2} ${field_2}
   *   {Description of field_2}.
   */
  public function __construct({type_1} ${field_1}, {type_2} ${field_2} /*, ... */) {
    $this->{field_1} = ${field_1};
    $this->{field_2} = ${field_2};
  }

  // Getter and setter for each field with proper PHPDoc...

  /**
   * Serialize this item to a plain array.
   *
   * @return array
   *   The item as an associative array.
   */
  public function toArray(): array {
    return [
      '{field_1}' => $this->{field_1},
      '{field_2}' => $this->{field_2},
      // ...
    ];
  }

}
```

**Critical rules:**
- This is a plain data class - no interface to implement.
- Field names, types, and helper methods MUST be derived from the user's answer in Step 0 question 7, not copied from classification examples.
- Each field needs a getter and setter with proper PHPDoc.
- Add a `toArray()` method so the parent `Output::toArray()` can serialize the item to scalars (see Step 3).
- Keep it simple - these are value objects.

**Reference examples (use to see *structure*, not to copy field names):**
- `src/OperationType/ImageClassification/ImageClassificationItem.php` - classification-shaped item (label + confidence score)
- `src/OperationType/Moderation/ModerationResponse.php` - response with flagged boolean + information array

If the operation is *not* classification-shaped, the reference files above are **structural** references only - match their class layout, not their field names.

## Step 5: Register the operation type in the config schema

Add the new operation type ID under `ai.settings.default_providers` in `config/schema/ai.schema.yml` so that administrators can configure a default provider for it.

Open `config/schema/ai.schema.yml` and add a new entry inside the `ai.settings` → `default_providers` → `mapping` block, keeping entries in **alphabetical order** by operation type ID:

```yaml
        {operation_type_id}:
          label: 'Default {human readable label lowercase} provider'
          type: ai.default_operation_provider
```

For example, adding `extractive_question_answering` would go between `embeddings` and `image_and_audio_to_video`:

```yaml
        embeddings:
          label: 'Default embeddings provider'
          type: ai.default_operation_provider
        extractive_question_answering:
          label: 'Default extractive question answering provider'
          type: ai.default_operation_provider
        image_and_audio_to_video:
          label: 'Default image and audio to video provider'
          type: ai.default_operation_provider
```

**Critical rules:**
- The key MUST exactly match the `{operation_type_id}` used in the `#[OperationType]` attribute on the interface (snake_case, `[a-z0-9_]` only).
- Copy the `type` value from any existing neighbour entry - they all share the same schema type, no need to invent a new one.
- Insert the entry in alphabetical order among the existing ones.
- Do not touch any other section of the schema file.

## Step 6: Update EchoAI Test Provider

The EchoAI test provider at `tests/modules/ai_test/src/Plugin/AiProvider/EchoProvider.php` must be updated to support the new operation type so that kernel tests can exercise it end-to-end. Three changes are required:

### 6a: Add use statements

Add `use` imports for the new Interface, Input, Output, and Item/Response classes at the top of `EchoProvider.php`, alongside the existing operation type imports.

```php
use Drupal\ai\OperationType\{OperationName}\{OperationName}Input;
use Drupal\ai\OperationType\{OperationName}\{OperationName}Interface;
use Drupal\ai\OperationType\{OperationName}\{OperationName}Item; // if applicable
use Drupal\ai\OperationType\{OperationName}\{OperationName}Output;
```

### 6b: Add the interface to the class declaration

Add `{OperationName}Interface` to the `implements` list on the `EchoProvider` class:

```php
class EchoProvider extends AiProviderClientBase implements
  ChatInterface,
  // ... existing interfaces ...
  {OperationName}Interface {
```

### 6c: Register the operation type ID

Add `'{operation_type_id}'` to the array returned by `getSupportedOperationTypes()`:

```php
public function getSupportedOperationTypes(): array {
  return [
    // ... existing types ...
    '{operation_type_id}',
  ];
}
```

### 6d: Implement the primary method

Add the method implementation. Follow the pattern of existing methods - the EchoAI provider should return predictable, deterministic output suitable for assertions in tests.

**For item-array operations** - where the output is an array of Item objects - follow the `imageClassification()` shape, but substitute the real Item constructor arguments (they will not always be `label` + score). Example (classification-shaped):

```php
public function {methodName}(string|{OperationName}Input $input, string $model_id, array $tags = []): {OperationName}Output {
  $output = [];
  $response = [];
  if ($input instanceof {OperationName}Input) {
    $labels = $input->getLabels();
    foreach ($labels as $label) {
      $output[] = new {OperationName}Item($label, 0.5);
      $response[] = [
        'label' => $label,
        'confidence' => 0.5,
      ];
    }
  }

  return new {OperationName}Output($output, $response, []);
}
```

**For simple text-in/text-out operations**, follow the `moderation()` pattern:

```php
public function {methodName}(string|{OperationName}Input $input, string $model_id, array $tags = []): {OperationName}Output {
  $response = [
    'input' => sprintf('Hello world! %s', (string) $input),
  ];
  // Create appropriate normalized output...
  return new {OperationName}Output($normalized, $response, []);
}
```

**Critical rules:**
- The method signature MUST match what the interface declares.
- The EchoAI provider should throw `AiBadRequestException` for invalid input (e.g. empty text, missing required data). Check how the kernel test's "broken" test case expects errors and ensure the provider validates accordingly.
- Return deterministic values that are easy to assert against in tests (e.g. confidence of `0.5` for all labels).
- Keep the implementation minimal - this is a test double, not a real provider.

**Reference implementations in EchoProvider:**
- `imageClassification()` - classification with labels and items
- `moderation()` - simple text input with response object
- `textToImage()` - file-based output
- `echo()` - minimal echo implementation

## Step 7: Generate Unit Tests

Generate unit tests for ALL generated classes. Tests go in `tests/src/Unit/OperationType/{OperationName}/`.

### 7a: Input Test

Create `tests/src/Unit/OperationType/{OperationName}/{OperationName}InputTest.php`.

**Pattern:**

```php
<?php

namespace Drupal\Tests\ai\Unit\OperationType\{OperationName};

use Drupal\ai\OperationType\{OperationName}\{OperationName}Input;
use PHPUnit\Framework\TestCase;

/**
 * Tests that the input functions function works.
 *
 * @group ai
 * @covers \Drupal\ai\OperationType\{OperationName}\{OperationName}Input
 */
class {OperationName}InputTest extends TestCase {

  /**
   * Test getting and setting for the input.
   */
  public function testGetSet(): void {
    $input = $this->getInput();
    // Assert initial values from constructor...
    // Test each setter and verify with getter...
  }

  /**
   * Test the toString method.
   */
  public function testToString(): void {
    $input = $this->getInput();
    $this->assertIsString($input->toString());
    // Assert expected string value...
  }

  /**
   * Test the toArray method.
   */
  public function testToArray(): void {
    $input = $this->getInput();
    $array = $input->toArray();
    $this->assertIsArray($array);
    // Assert expected keys and values...
  }

  /**
   * Helper function to get the input.
   *
   * @return \Drupal\ai\OperationType\{OperationName}\{OperationName}Input
   *   The input.
   */
  public function getInput(): {OperationName}Input {
    return new {OperationName}Input(/* constructor args */);
  }

}
```

### 7b: Output Test

Create `tests/src/Unit/OperationType/{OperationName}/{OperationName}OutputTest.php`.

**Pattern:**

```php
<?php

namespace Drupal\Tests\ai\Unit\OperationType\{OperationName};

use Drupal\ai\OperationType\{OperationName}\{OperationName}Output;
use PHPUnit\Framework\TestCase;

/**
 * Tests that the output functions function works.
 *
 * @group ai
 * @covers \Drupal\ai\OperationType\{OperationName}\{OperationName}Output
 */
class {OperationName}OutputTest extends TestCase {

  /**
   * Test getting and setting for the output.
   */
  public function testGetSet(): void {
    $output = $this->getOutput();
    // Assert getNormalized() returns expected value...
    // Assert getRawOutput() returns expected value...
    // Assert getMetadata() returns expected value...
  }

  /**
   * Test the toArray method.
   */
  public function testToArray(): void {
    $output = $this->getOutput();
    $array = $output->toArray();
    $this->assertIsArray($array);
    $this->assertArrayHasKey('normalized', $array);
    $this->assertArrayHasKey('rawOutput', $array);
    $this->assertArrayHasKey('metadata', $array);
  }

  /**
   * Helper function to get the output.
   *
   * @return \Drupal\ai\OperationType\{OperationName}\{OperationName}Output
   *   The output.
   */
  public function getOutput(): {OperationName}Output {
    return new {OperationName}Output(/* normalized */, /* rawOutput */, /* metadata */);
  }

}
```

### 7c: Item/Response Test (if an item class was generated)

Create `tests/src/Unit/OperationType/{OperationName}/{OperationName}ItemTest.php`.

**Pattern:**

```php
<?php

namespace Drupal\Tests\ai\Unit\OperationType\{OperationName};

use Drupal\ai\OperationType\{OperationName}\{OperationName}Item;
use PHPUnit\Framework\TestCase;

/**
 * Tests that the item functions function works.
 *
 * @group ai
 * @covers \Drupal\ai\OperationType\{OperationName}\{OperationName}Item
 */
class {OperationName}ItemTest extends TestCase {

  /**
   * Test getting and setting for the item.
   */
  public function testGetSet(): void {
    $item = $this->getItem();
    // Assert initial values from constructor...
    // Test each setter and verify with getter...
  }

  /**
   * Helper function to get the item.
   *
   * @return \Drupal\ai\OperationType\{OperationName}\{OperationName}Item
   *   The item.
   */
  public function getItem(): {OperationName}Item {
    return new {OperationName}Item(/* constructor args */);
  }

}
```

**Reference test examples:**
- `tests/src/Unit/OperationType/TextToImage/TextToImageInputTest.php` - simple input test
- `tests/src/Unit/OperationType/Moderation/ModerationInputTest.php` - simple input test
- `tests/src/Unit/OperationType/Moderation/ModerationOutputTest.php` - output test with complex Response object
- `tests/src/Unit/OperationType/ImageClassification/ImageClassificationOutputTest.php` - output test with Item objects
- `tests/src/Unit/OperationType/ImageClassification/ImageClassificationInputTest.php` - input test with ImageFile

### 7d: Kernel Test (Interface Integration Test)

Create `tests/src/Kernel/OperationType/{OperationName}/{OperationName}InterfaceTest.php`.

This test validates the full operation type works end-to-end with the EchoAI test provider. It extends `KernelTestBase`, boots the `ai` and `ai_test` modules, instantiates the EchoAI provider, and exercises the primary method.

**IMPORTANT:** Step 6 must be completed before this step - the EchoAI provider must already implement the new interface and its primary method for this test to work.

**Pattern:**

```php
<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Kernel\OperationType\{OperationName};

use Drupal\KernelTests\KernelTestBase;
use Drupal\ai\OperationType\{OperationName}\{OperationName}Input;
use Drupal\ai\OperationType\{OperationName}\{OperationName}Output;
{additional use statements for Item classes if applicable}

/**
 * This tests the {Human Readable Label} calling.
 *
 * @coversDefaultClass \Drupal\ai\OperationType\{OperationName}\{OperationName}Interface
 *
 * @group ai
 */
class {OperationName}InterfaceTest extends KernelTestBase {

  /**
   * Model for the setup.
   *
   * @var string
   */
  protected $model;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'ai',
    'ai_test',
    'key',
    'file',
    'system',
  ];

  /**
   * Test the {operation} with normal input.
   */
  public function test{OperationName}Normal(): void {
    $provider = \Drupal::service('ai.provider')->createInstance('echoai');
    $input = new {OperationName}Input({example constructor args});
    // Set any additional input properties if needed.

    $result = $provider->{methodName}($input, 'test');
    // Should be a {OperationName}Output object.
    $this->assertInstanceOf({OperationName}Output::class, $result);

    $normalized = $result->getNormalized();
    // Assert the normalized output structure and values.
    // For array outputs:
    $this->assertIsArray($normalized);
    // For item-based outputs, check count and item types:
    // $this->assertInstanceOf({OperationName}Item::class, $normalized[0]);
    // $this->assertEquals('expected_label', $normalized[0]->getLabel());
  }

  /**
   * Test the {operation} without a model.
   */
  public function test{OperationName}Broken(): void {
    $provider = \Drupal::service('ai.provider')->createInstance('echoai');
    $input = new {OperationName}Input({example constructor args});
    $this->expectException(\Drupal\ai\Exception\AiBadRequestException::class);
    $provider->{methodName}($input, $this->model);
  }

}
```

**Critical rules:**
- MUST extend `KernelTestBase`.
- MUST enable `ai`, `ai_test`, `key`, `file`, and `system` modules at minimum.
- MUST use `\Drupal::service('ai.provider')->createInstance('echoai')` to get the EchoAI provider. This returns a `ProviderProxy`, not the raw provider.
- Add additional modules to `$modules` only if needed (e.g. `media`, `user`, `image` for file/media operations).
- Include both a "normal" test and a "broken" (error condition) test.
- For the broken test: declare an uninitialized `protected $model;` property (which is `NULL`) and pass `$this->model` as the model argument. The `ProviderProxy` throws `AiBadRequestException` when the model ID is not a string. This is the established pattern - see `ImageClassificationInterfaceTest`.
- Add `use` imports for `{OperationName}Item` if the output contains item objects.
- Add `declare(strict_types=1);` at the top.

**Reference kernel test examples:**
- `tests/src/Kernel/OperationType/ImageClassification/ImageClassificationInterfaceTest.php` - tests classification with labels, item assertions, and broken input
- `tests/src/Kernel/OperationType/TextToImage/TextToImageInterfaceTest.php` - tests with file output, multiple models, raw input

## Step 8: Run Verification Script

**MANDATORY:** Before marking the task as complete, run the verification script to validate all generated files.

```bash
php .agents/skills/create-operation-type/verify-operation-type.php {OperationName}
```

Where `{OperationName}` is the PascalCase name (e.g. `ImageClassification`, `TextToImage`).

The script checks:
1. Interface file exists and extends `OperationTypeInterface`
2. Input file exists and extends `InputBase` / implements `InputInterface`
3. Output file exists and implements `OutputInterface`
4. The `#[OperationType]` attribute exists in the interface with:
   - An `id` using only `[a-z0-9_]` (alphanumeric + underscore, no dashes)
   - A `label` using `new TranslatableMarkup(...)`
5. The interface method signature references both the Input and Output classes
6. Unit test files exist for input and output (and item if applicable)
7. Kernel test file exists, extends `KernelTestBase`, uses `echoai` provider, references Output class
8. EchoProvider implements the new interface and registers the operation type ID in `getSupportedOperationTypes()`

**If the script reports any errors, fix them before proceeding.**

## Step 9: Create Developer Documentation

Create a documentation page at `docs/developers/call_{operation_type_id}.md` and link it in `mkdocs.yml`.

### 9a: Create the documentation file

Create `docs/developers/call_{operation_type_id}.md` following the pattern used by existing operation type docs.

**Pattern:**

```markdown


## {Human Readable Label}

{One or two sentences describing what this operation type does and what kind of AI providers support it.}

### Example normalized {Human Readable Label} call

{One or two sentences describing the example scenario: what input is being sent, to which provider/model, and what the output is.}

\```php
use Drupal\ai\OperationType\{OperationName}\{OperationName}Input;

$input = new {OperationName}Input({example constructor args});
/** @var \Drupal\ai\OperationType\{OperationName}\{OperationName}Output ${output_var} */
${output_var} = \Drupal::service('ai.provider')->createInstance('{example_provider}')->{methodName}($input, '{example_model}', ['my-custom-call']);

{Example of accessing the normalized output, e.g. getNormalized(), iterating items, etc.}
\```

### {Human Readable Label} Interfaces & Models

The following files defines the methods available when doing a {human readable label} call as well as the input and output.

* [{OperationName}Interface.php](https://git.drupalcode.org/project/ai/-/blob/HEAD/src/OperationType/{OperationName}/{OperationName}Interface.php)
* [{OperationName}Input.php](https://git.drupalcode.org/project/ai/-/blob/HEAD/src/OperationType/{OperationName}/{OperationName}Input.php)
* [{OperationName}Output.php](https://git.drupalcode.org/project/ai/-/blob/HEAD/src/OperationType/{OperationName}/{OperationName}Output.php)

### {Human Readable Label} Explorer
If you install the AI API Explorer, you can go `configuration > AI > AI API Explorer > {Human Readable Label} Explorer` under `/admin/config/ai/explorers/ai-{url-slug}` to test out different calls and see the code that you need for it.
```

**Critical rules:**
- The filename MUST be `call_{operation_type_id}.md` (using the snake_case id, e.g. `call_text_to_image.md`).
- The code example MUST be realistic and show how to construct the input, make the call, and access the output.
- If an Item/Response class exists, the example should show how to work with it (e.g. iterating items, accessing properties).
- Link to source files using the `HEAD` branch on git.drupalcode.org.

**Reference examples:**
- `docs/developers/call_text_to_image.md` - simple operation with ImageFile output
- `docs/developers/call_image_classification.md` - operation with Item objects in output
- `docs/developers/call_moderation.md` - operation with a Response object in output

### 9b: Add the page to mkdocs.yml

Add the new documentation page to the `nav` section in `mkdocs.yml`, under `Develop > Making AI Calls`.

Insert the entry in **alphabetical order** among the existing call entries. The format is:

```yaml
      - {Human Readable Label} Call: developers/call_{operation_type_id}.md
```

For example, if adding "Object Detection", it goes between "Moderation Call" and "Speech-To-Speech Call":
```yaml
      - Moderation Call: developers/call_moderation.md
      - Object Detection Call: developers/call_object_detection.md
      - Speech-To-Speech Call: developers/call_speech_to_speech.md
```

**Critical rules:**
- Insert alphabetically among existing entries in the `Making AI Calls` section.
- The label must end with "Call" (e.g. "Text To Image Call", "Image Classification Call").
- The path must match the filename created in step 8a.

## Step 10: Remind About Provider Implementation

After all files are generated and verified, inform the user:

> The operation type `{OperationName}` has been scaffolded. To complete the integration:
>
> 1. **Provider implementation**: Any AI provider that supports this operation must implement `{OperationName}Interface` and add the operation type to their `getSupportedOperationTypes()` method.
> 2. **Discovery**: The `OperationTypeDiscovery` class will automatically discover the new operation type from the `#[OperationType]` attribute on the interface.
> 3. **Testing**: Run the generated unit tests with:
>    ```bash
>    php vendor/bin/phpunit tests/src/Unit/OperationType/{OperationName}/
>    ```
> 4. **Documentation**: The developer documentation has been added at `docs/developers/call_{operation_type_id}.md` and linked in `mkdocs.yml`.

## Summary of Generated Files

For an operation type named `{OperationName}`:

| File | Purpose |
|------|---------|
| `src/OperationType/{OperationName}/{OperationName}Interface.php` | Interface with `#[OperationType]` attribute |
| `src/OperationType/{OperationName}/{OperationName}Input.php` | Input class extending `InputBase` |
| `src/OperationType/{OperationName}/{OperationName}Output.php` | Output class implementing `OutputInterface` |
| `src/OperationType/{OperationName}/{OperationName}Item.php` | *(Optional)* Data class for complex output items |
| `tests/src/Unit/OperationType/{OperationName}/{OperationName}InputTest.php` | Unit test for input |
| `tests/src/Unit/OperationType/{OperationName}/{OperationName}OutputTest.php` | Unit test for output |
| `tests/src/Unit/OperationType/{OperationName}/{OperationName}ItemTest.php` | *(Optional)* Unit test for item class |
| `tests/src/Kernel/OperationType/{OperationName}/{OperationName}InterfaceTest.php` | Kernel integration test with EchoAI provider |
| `tests/modules/ai_test/src/Plugin/AiProvider/EchoProvider.php` | *(Updated)* Implements new interface and method |
| `config/schema/ai.schema.yml` | *(Updated)* New entry added under `ai.settings.default_providers` |
| `docs/developers/call_{operation_type_id}.md` | Developer documentation |
| `mkdocs.yml` | *(Updated)* Nav entry added under Making AI Calls |
