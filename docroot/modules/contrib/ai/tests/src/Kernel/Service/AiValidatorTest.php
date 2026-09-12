<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Kernel\Service;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ai\Service\AiValidatorInterface;

/**
 * Tests the AiValidator service.
 *
 * @group ai
 */
class AiValidatorTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai',
    'system',
  ];

  /**
   * The AI validator service.
   *
   * @var \Drupal\ai\Service\AiValidatorInterface
   */
  protected AiValidatorInterface $validator;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Install the module.
    $this->installConfig(['ai']);
    $this->validator = $this->container?->get(AiValidatorInterface::class);
  }

  /**
   * Test validation with valid data.
   */
  public function testValidationValid(): void {
    $schema = '{
      "type": "object",
      "properties": {
        "name": { "type": "string" },
        "age": { "type": "integer" }
      },
      "required": ["name"]
    }';

    $json_data = '{"name": "John", "age": 30}';
    $errors = $this->validator->validateJsonSchema($json_data, $schema);
    $this->assertEmpty($errors, 'Valid JSON data should not return errors.');

    $yaml_data = "name: John\nage: 30";
    $errors = $this->validator->validateJsonSchema($yaml_data, $schema);
    $this->assertEmpty($errors, 'Valid YAML data should not return errors.');
  }

  /**
   * Test validation with invalid data content.
   */
  public function testValidationInvalidContent(): void {
    $schema = '{
      "type": "object",
      "properties": {
        "age": { "type": "integer", "maximum": 100 }
      }
    }';

    $json_data = '{"age": 150}';
    $errors = $this->validator->validateJsonSchema($json_data, $schema);
    $this->assertNotEmpty($errors, 'Invalid data content should return errors.');
    // Check for generic error or specific error.
    $json_encoded_error = json_encode($errors, JSON_THROW_ON_ERROR);
    $this->assertTrue(
        str_contains($json_encoded_error, 'less than or equal to 100') ||
        str_contains($json_encoded_error, 'properties must match schema') ||
        str_contains($json_encoded_error, 'Must have a maximum value'),
        'Error message should indicate validation failure: ' . $json_encoded_error
    );
  }

  /**
   * Test validation with invalid format (bad JSON/YAML).
   */
  public function testValidationInvalidFormat(): void {
    $schema = '{}';
    // Invalid JSON (keys must be quoted)
    // '{name: "harivansh"}';
    // Note: YAML parser might parse this as valid YAML if we are not careful,
    // but the validator treats strings as JSON first, then YAML.
    $bad_format = '{';
    $errors = $this->validator->validateJsonSchema($bad_format, $schema);
    $this->assertNotEmpty($errors);
    $this->assertStringContainsString('Invalid JSON or YAML format', $errors[0]);
  }

  /**
   * Test basic helpers.
   */
  public function testHelpers(): void {
    $this->assertTrue($this->validator->isValidJson('{"a":1}'));
    $this->assertFalse($this->validator->isValidJson('{a:1}'));

    $this->assertTrue($this->validator->isValidYaml("a: 1"));
    $this->assertFalse($this->validator->isValidYaml("\ttabs: not allowed in yaml"));
  }

}
