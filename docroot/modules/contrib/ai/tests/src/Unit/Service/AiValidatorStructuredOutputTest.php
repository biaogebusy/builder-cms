<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Unit\Service;

use Drupal\ai\Service\AiValidator;
use Drupal\Tests\UnitTestCase;

/**
 * Tests the AiValidator service for structured output validation.
 *
 * @group ai
 * @coversDefaultClass \Drupal\ai\Service\AiValidator
 */
class AiValidatorStructuredOutputTest extends UnitTestCase {

  /**
   * The AI validator service.
   *
   * @var \Drupal\ai\Service\AiValidator
   */
  protected AiValidator $validator;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Mock the string translation service.
    $string_translation = $this->getStringTranslationStub();

    $this->validator = new AiValidator();
    $this->validator->setStringTranslation($string_translation);
  }

  /**
   * Tests validation of a valid structured output schema.
   */
  public function testValidStructuredOutputSchema(): void {
    $schema = [
      'name' => 'test_schema',
      'description' => 'Test schema',
      'strict' => TRUE,
      'json_schema' => [
        'type' => 'object',
        'properties' => [
          'name' => ['type' => 'string'],
          'age' => ['type' => 'integer'],
        ],
        'required' => ['name', 'age'],
      ],
    ];

    $errors = $this->validator->validateStructuredOutputSchema($schema);
    $this->assertEmpty($errors, 'Valid schema should have no errors.');
  }

  /**
   * Tests validation with missing name.
   */
  public function testMissingName(): void {
    $schema = [
      'description' => 'Test schema',
      'json_schema' => [
        'type' => 'object',
        'properties' => ['name' => ['type' => 'string']],
        'required' => ['name'],
      ],
    ];

    $errors = $this->validator->validateStructuredOutputSchema($schema);
    $this->assertNotEmpty($errors);
    $this->assertStringContainsString('name is required', implode(' ', $errors));
  }

  /**
   * Tests validation with invalid name format.
   */
  public function testInvalidNameFormat(): void {
    $schema = [
      'name' => 'Invalid Name With Spaces',
      'json_schema' => [
        'type' => 'object',
        'properties' => ['name' => ['type' => 'string']],
        'required' => ['name'],
      ],
    ];

    $errors = $this->validator->validateStructuredOutputSchema($schema);
    $this->assertNotEmpty($errors);
    $this->assertStringContainsString('lowercase letters', implode(' ', $errors));
  }

  /**
   * Tests validation with missing json_schema.
   */
  public function testMissingJsonSchema(): void {
    $schema = [
      'name' => 'test_schema',
      'description' => 'Test schema',
    ];

    $errors = $this->validator->validateStructuredOutputSchema($schema);
    $this->assertNotEmpty($errors);
    $this->assertStringContainsString('json_schema is required', implode(' ', $errors));
  }

  /**
   * Tests validation with empty json_schema.
   */
  public function testEmptyJsonSchema(): void {
    $schema = [
      'name' => 'test_schema',
      'json_schema' => [],
    ];

    $errors = $this->validator->validateStructuredOutputSchema($schema);
    $this->assertNotEmpty($errors);
    $this->assertStringContainsString('cannot be empty', implode(' ', $errors));
  }

  /**
   * Tests JSON schema structure validation.
   */
  public function testValidJsonSchemaStructure(): void {
    $json_schema = [
      'type' => 'object',
      'properties' => [
        'name' => ['type' => 'string'],
        'email' => ['type' => 'string'],
      ],
      'required' => ['name', 'email'],
    ];

    $errors = $this->validator->validateJsonSchemaStructure($json_schema, TRUE);
    $this->assertEmpty($errors, 'Valid JSON schema should have no errors.');
  }

  /**
   * Tests JSON schema without type.
   */
  public function testJsonSchemaWithoutType(): void {
    $json_schema = [
      'properties' => [
        'name' => ['type' => 'string'],
      ],
    ];

    $errors = $this->validator->validateJsonSchemaStructure($json_schema, TRUE);
    $this->assertNotEmpty($errors);
    $this->assertStringContainsString('must have a "type"', implode(' ', $errors));
  }

  /**
   * Tests JSON schema with missing required fields (AI constraint).
   */
  public function testJsonSchemaWithMissingRequired(): void {
    $json_schema = [
      'type' => 'object',
      'properties' => [
        'name' => ['type' => 'string'],
        'email' => ['type' => 'string'],
      ],
      'required' => ['name'],
      // Missing 'email' in required.
    ];

    $errors = $this->validator->validateJsonSchemaStructure($json_schema, TRUE);
    $this->assertNotEmpty($errors);
    $this->assertStringContainsString('must be marked as required', implode(' ', $errors));
  }

  /**
   * Tests JSON schema with anyOf at root (AI constraint).
   */
  public function testJsonSchemaWithAnyOfAtRoot(): void {
    $json_schema = [
      'anyOf' => [
        ['type' => 'object', 'properties' => ['a' => ['type' => 'string']]],
        ['type' => 'object', 'properties' => ['b' => ['type' => 'string']]],
      ],
    ];

    $errors = $this->validator->validateJsonSchemaStructure($json_schema, TRUE);
    $this->assertNotEmpty($errors);
    $this->assertStringContainsString('must be an object, not anyOf', implode(' ', $errors));
  }

  /**
   * Tests JSON schema with non-object root type (AI constraint).
   */
  public function testJsonSchemaWithNonObjectRoot(): void {
    $json_schema = [
      'type' => 'array',
      'items' => ['type' => 'string'],
    ];

    $errors = $this->validator->validateJsonSchemaStructure($json_schema, TRUE);
    $this->assertNotEmpty($errors);
    $this->assertStringContainsString('must be of type "object"', implode(' ', $errors));
  }

  /**
   * Tests JSON schema with nested object missing required fields.
   */
  public function testNestedObjectMissingRequired(): void {
    $json_schema = [
      'type' => 'object',
      'properties' => [
        'user' => [
          'type' => 'object',
          'properties' => [
            'name' => ['type' => 'string'],
            'email' => ['type' => 'string'],
          ],
          'required' => ['name'],
          // Missing 'email' in required.
        ],
      ],
      'required' => ['user'],
    ];

    $errors = $this->validator->validateJsonSchemaStructure($json_schema, TRUE);
    $this->assertNotEmpty($errors);
    $this->assertStringContainsString('user', implode(' ', $errors));
    $this->assertStringContainsString('must be marked as required', implode(' ', $errors));
  }

  /**
   * Tests JSON schema validation without AI constraints.
   */
  public function testJsonSchemaWithoutAiConstraints(): void {
    $json_schema = [
      'type' => 'object',
      'properties' => [
        'name' => ['type' => 'string'],
        'email' => ['type' => 'string'],
      ],
      'required' => ['name'],
      // Missing 'email' in required, but AI constraints disabled.
    ];

    $errors = $this->validator->validateJsonSchemaStructure($json_schema, FALSE);
    // Should pass basic validation without AI constraints.
    $this->assertEmpty($errors, 'Schema should be valid when AI constraints are disabled.');
  }

  /**
   * Tests JSON schema from string.
   */
  public function testJsonSchemaFromString(): void {
    $json_string = json_encode([
      'type' => 'object',
      'properties' => [
        'name' => ['type' => 'string'],
      ],
      'required' => ['name'],
    ]);

    $errors = $this->validator->validateJsonSchemaStructure($json_string, TRUE);
    $this->assertEmpty($errors, 'Valid JSON string schema should have no errors.');
  }

  /**
   * Tests invalid JSON string throws JsonException.
   */
  public function testInvalidJsonString(): void {
    $json_string = '{invalid json}';

    $errors = $this->validator->validateJsonSchemaStructure($json_string, TRUE);
    $this->assertNotEmpty($errors, 'Invalid JSON should produce errors.');
    $this->assertCount(1, $errors, 'Should have exactly one error.');
    $this->assertStringContainsString('Invalid JSON', $errors[0], 'Error should mention invalid JSON.');
    $this->assertStringContainsString('Syntax error', $errors[0], 'Error should include JsonException message.');
  }

  /**
   * Tests malformed JSON with various syntax errors.
   *
   * @dataProvider malformedJsonProvider
   */
  public function testMalformedJsonHandling(string $malformed_json, string $expected_error_fragment): void {
    $errors = $this->validator->validateJsonSchemaStructure($malformed_json, TRUE);
    $this->assertNotEmpty($errors, 'Malformed JSON should produce errors.');
    $this->assertStringContainsString('Invalid JSON', implode(' ', $errors), 'Error should mention invalid JSON.');
    $this->assertStringContainsString($expected_error_fragment, implode(' ', $errors), 'Error should contain expected fragment.');
  }

  /**
   * Data provider for malformed JSON test cases.
   *
   * @return array<string, array<string, string>>
   *   Test cases with malformed JSON and expected error fragments.
   */
  public static function malformedJsonProvider(): array {
    return [
      'missing closing brace' => [
        'malformed_json' => '{"type": "object"',
        'expected_error_fragment' => 'Syntax error',
      ],
      'trailing comma' => [
        'malformed_json' => '{"type": "object",}',
        'expected_error_fragment' => 'Syntax error',
      ],
      'unquoted key' => [
        'malformed_json' => '{type: "object"}',
        'expected_error_fragment' => 'Syntax error',
      ],
      'single quotes instead of double' => [
        'malformed_json' => "{'type': 'object'}",
        'expected_error_fragment' => 'Syntax error',
      ],
    ];
  }

}
