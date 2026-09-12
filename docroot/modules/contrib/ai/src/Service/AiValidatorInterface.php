<?php

declare(strict_types=1);

namespace Drupal\ai\Service;

/**
 * Interface for AI Validator service.
 */
interface AiValidatorInterface {

  /**
   * Validates data against a JSON schema.
   *
   * @param mixed $data
   *   The data to validate. If string, it tries to parse as JSON first.
   * @param mixed $schema
   *   The schema to validate against. Can be a JSON string, array, or object.
   * @param int|null $checkMode
   *   The validation check mode. Defaults to Constraint::CHECK_MODE_NORMAL.
   *   See \JsonSchema\Constraints\Constraint for available modes.
   *
   * @return array<string>
   *   An array of error messages. Empty if valid.
   */
  public function validateJsonSchema(mixed $data, mixed $schema, ?int $checkMode = NULL): array;

  /**
   * Validates YAML string.
   *
   * @param string $yaml
   *   The YAML string to check.
   *
   * @return bool
   *   TRUE if valid YAML, FALSE otherwise.
   */
  public function isValidYaml(string $yaml): bool;

  /**
   * Validates JSON string.
   *
   * @param string $json
   *   The JSON string to check.
   *
   * @return bool
   *   TRUE if valid JSON, FALSE otherwise.
   *
   * @throws \JsonException
   */
  public function isValidJson(string $json): bool;

  /**
   * Validates the complete structured output schema.
   *
   * @param array<string, mixed> $schema
   *   The structured output schema array with keys:
   *   - name: Schema name (required, lowercase alphanumeric with _ and -)
   *   - description: Schema description (optional)
   *   - strict: Whether strict mode is enabled (optional, defaults to FALSE)
   *   - json_schema: The JSON schema definition (required, must be valid).
   * @param bool $check_ai_constraints
   *   Whether to check AI provider-specific constraints. Defaults to TRUE.
   *
   * @return array<string>
   *   An array of error messages. Empty if valid.
   */
  public function validateStructuredOutputSchema(array $schema, bool $check_ai_constraints = TRUE): array;

  /**
   * Validates that the provided schema.
   *
   * @param mixed $schema
   *   The JSON schema to validate. Can be array, object, or JSON string.
   * @param bool $check_ai_constraints
   *   Whether to check AI provider-specific constraints like:
   *   - All fields must be required
   *   - Root must be an object (not anyOf)
   *   - No unsupported features.
   *
   * @return array<string>
   *   An array of error messages. Empty if valid.
   */
  public function validateJsonSchemaStructure(mixed $schema, bool $check_ai_constraints = TRUE): array;

}
