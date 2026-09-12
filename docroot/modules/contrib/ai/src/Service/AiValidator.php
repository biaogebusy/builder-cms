<?php

declare(strict_types=1);

namespace Drupal\ai\Service;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use JsonSchema\Constraints\Constraint;
use JsonSchema\Validator;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

/**
 * Service to validate AI structured content.
 *
 * Uses justinrainbow/json-schema (bundled with Drupal core via Composer)
 * for data-against-schema validation, ensuring 1.3.x backport compatibility
 * without introducing new dependencies.
 */
class AiValidator implements AiValidatorInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function validateJsonSchema(mixed $data, mixed $schema, ?int $checkMode = NULL): array {
    if (is_string($data)) {
      try {
        $parsed = json_decode($data, FALSE, 512, JSON_THROW_ON_ERROR);
      }
      catch (\JsonException) {
        try {
          // We need the specific YAML exception to catch here.
          // @phpstan-ignore-next-line
          $parsed = Yaml::parse($data, Yaml::PARSE_OBJECT_FOR_MAP);
        }
        catch (ParseException $ymlException) {
          return [(string) $this->t('Invalid JSON or YAML format: @error', ['@error' => $ymlException->getMessage()])];
        }
      }
      $data = $parsed;
    }

    // justinrainbow/json-schema requires stdClass objects, not arrays.
    if (is_array($data)) {
      $encoded = json_encode($data, JSON_THROW_ON_ERROR);
      if ($encoded !== FALSE) {
        $data = json_decode($encoded, FALSE, 512, JSON_THROW_ON_ERROR);
      }
    }

    try {
      if (is_string($schema)) {
        $schema = json_decode($schema, FALSE, 512, JSON_THROW_ON_ERROR);
      }

      if (is_array($schema)) {
        $encoded = json_encode($schema, JSON_THROW_ON_ERROR);
        if ($encoded !== FALSE) {
          $schema = json_decode($encoded, FALSE, 512, JSON_THROW_ON_ERROR);
        }
      }
    }
    catch (\JsonException $e) {
      return [(string) $this->t('Invalid schema format: @error', ['@error' => $e->getMessage()])];
    }

    $validator = new Validator();

    try {
      $validator->validate($data, $schema, $checkMode ?? Constraint::CHECK_MODE_NORMAL);
    }
    catch (\Exception $e) {
      return [(string) $this->t('Schema validation error: @error', ['@error' => $e->getMessage()])];
    }

    if ($validator->isValid()) {
      return [];
    }

    $errors = [];
    foreach ($validator->getErrors() as $error) {
      $pointer = $error['property'] ?? '';
      $pointer = $pointer !== '' ? $pointer : 'root';
      $message = $error['message'] ?? 'Unknown validation error';
      $errors[] = sprintf("[%s] %s", $pointer, $message);
    }
    return $errors;
  }

  /**
   * {@inheritdoc}
   */
  public function isValidYaml(string $yaml): bool {
    try {
      // @phpstan-ignore-next-line
      Yaml::parse($yaml);
      return TRUE;
    }
    catch (ParseException $e) {
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function isValidJson(string $json): bool {
    try {
      json_decode($json, TRUE, 512, JSON_THROW_ON_ERROR);
      return TRUE;
    }
    catch (\JsonException) {
      return FALSE;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validateStructuredOutputSchema(array $schema, bool $check_ai_constraints = TRUE): array {
    $errors = [];

    if (!isset($schema['name']) || $schema['name'] === '') {
      $errors[] = (string) $this->t('Schema name is required.');
    }
    elseif (!is_string($schema['name'])) {
      $errors[] = (string) $this->t('Schema name must be a string.');
    }
    elseif (!preg_match('/^[a-z0-9_-]+$/', $schema['name'])) {
      $errors[] = (string) $this->t('Schema name must contain only lowercase letters, numbers, underscores, and hyphens.');
    }

    if (array_key_exists('description', $schema) && !is_string($schema['description']) && $schema['description'] !== NULL) {
      $errors[] = (string) $this->t('Schema description must be a string or null.');
    }

    if (array_key_exists('strict', $schema) && !is_bool($schema['strict'])) {
      $errors[] = (string) $this->t('Schema strict must be a boolean.');
    }

    if (!isset($schema['json_schema'])) {
      $errors[] = (string) $this->t('json_schema is required.');
    }
    elseif (!is_array($schema['json_schema'])) {
      $errors[] = (string) $this->t('json_schema must be an array.');
    }
    elseif (empty($schema['json_schema'])) {
      $errors[] = (string) $this->t('json_schema cannot be empty.');
    }
    else {
      $schema_errors = $this->validateJsonSchemaStructure($schema['json_schema'], $check_ai_constraints);
      $errors = array_merge($errors, $schema_errors);
    }

    return $errors;
  }

  /**
   * {@inheritdoc}
   */
  public function validateJsonSchemaStructure(mixed $schema, bool $check_ai_constraints = TRUE): array {
    $errors = [];

    if (is_string($schema)) {
      try {
        $parsed = json_decode($schema, TRUE, 512, JSON_THROW_ON_ERROR);
        $schema = $parsed;
      }
      catch (\JsonException $e) {
        return [(string) $this->t('Invalid JSON: @error', ['@error' => $e->getMessage()])];
      }
    }

    if (is_object($schema)) {
      try {
        $encoded = json_encode($schema, JSON_THROW_ON_ERROR);
        $schema = json_decode($encoded, TRUE, 512, JSON_THROW_ON_ERROR);
      }
      catch (\JsonException $e) {
        return [(string) $this->t('Failed to encode schema: @error', ['@error' => $e->getMessage()])];
      }
    }

    if (!is_array($schema)) {
      return [(string) $this->t('Schema must be an array or object.')];
    }

    if (!isset($schema['type'])) {
      $errors[] = (string) $this->t('Schema must have a "type" property.');
    }

    if (isset($schema['type']) && $schema['type'] === 'object') {
      if (!isset($schema['properties']) || !is_array($schema['properties'])) {
        $errors[] = (string) $this->t('Object schema must have a "properties" field that is an array.');
      }
    }

    if ($check_ai_constraints) {
      $ai_errors = $this->validateAiProviderConstraints($schema);
      $errors = array_merge($errors, $ai_errors);
    }

    return $errors;
  }

  /**
   * Validates AI provider-specific constraints.
   *
   * @param array<string, mixed> $schema
   *   The JSON schema array.
   *
   * @return array<string>
   *   An array of error messages.
   */
  protected function validateAiProviderConstraints(array $schema): array {
    $errors = [];

    if (isset($schema['anyOf']) && !isset($schema['type'])) {
      $errors[] = (string) $this->t('Root level schema must be an object, not anyOf. AI providers do not support discriminated unions at the root level.');
    }

    if (isset($schema['type']) && $schema['type'] !== 'object') {
      $errors[] = (string) $this->t('Root level schema must be of type "object". AI providers require the root to be an object.');
    }

    if (isset($schema['properties']) && is_array($schema['properties'])) {
      $property_names = array_keys($schema['properties']);
      $required = isset($schema['required']) && is_array($schema['required']) ? $schema['required'] : [];

      if (count($property_names) !== count($required)) {
        $missing = array_diff($property_names, $required);
        if (!empty($missing)) {
          $errors[] = (string) $this->t('All properties must be marked as required for AI structured outputs. Missing: @fields', [
            '@fields' => implode(', ', $missing),
          ]);
        }
      }

      foreach ($schema['properties'] as $prop_name => $prop_schema) {
        if (isset($prop_schema['type'], $prop_schema['properties']) && is_array($prop_schema) && $prop_schema['type'] === 'object' && is_array($prop_schema['properties'])) {
          $nested_property_names = array_keys($prop_schema['properties']);
          $nested_required = isset($prop_schema['required']) && is_array($prop_schema['required']) ? $prop_schema['required'] : [];

          if (count($nested_property_names) !== count($nested_required)) {
            $missing = array_diff($nested_property_names, $nested_required);
            if (!empty($missing)) {
              $errors[] = (string) $this->t('All properties in "@property" must be marked as required. Missing: @fields', [
                '@property' => $prop_name,
                '@fields' => implode(', ', $missing),
              ]);
            }
          }
        }
      }
    }

    return $errors;
  }

}
