<?php

declare(strict_types=1);

namespace Drupal\ai\Dto;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Data transfer object for structured output schema.
 *
 * Use this DTO to define how the AI provider should structure its response
 * (e.g. JSON with specific properties). Pass it to
 * \Drupal\ai\OperationType\Chat\ChatInput::setChatStructuredJsonSchema() when
 * building a chat request.
 *
 * Usage with ChatInput:
 * @code
 * use Drupal\ai\Dto\StructuredOutputSchema;
 * use Drupal\ai\OperationType\Chat\ChatInput;
 * use Drupal\ai\OperationType\Chat\ChatMessage;
 *
 * $schema = new StructuredOutputSchema(
 *   name: 'weather_response',
 *   description: 'Structured weather data',
 *   strict: TRUE,
 *   json_schema: [
 *     'properties' => [
 *       'temperature' => ['type' => 'number'],
 *       'location' => ['type' => 'string'],
 *     ],
 *   ],
 * );
 *
 * $input = new ChatInput([new ChatMessage('user', 'What is the weather?')]);
 * $input->setChatStructuredJsonSchema($schema);
 * @endcode
 *
 * Alternatively, create from an array (e.g. from config or API):
 * @code
 * $schema = StructuredOutputSchema::fromArray([
 *   'name' => 'my_schema',
 *   'description' => 'Optional description',
 *   'strict' => FALSE,
 *   'schema' => [
 *     'properties' => [
 *       'answer' => ['type' => 'string'],
 *     ],
 *   ],
 * );
 * $input->setChatStructuredJsonSchema($schema);
 * @endcode
 *
 * Validation:
 * - name: Only lowercase letters, numbers, underscores, and hyphens. Defaults
 *   to "json_schema" if omitted in fromArray().
 * - schema (or json_schema): Must be a non-empty array with at least a
 *   'properties' key. The array shape uses 'schema' as the default key;
 *   'json_schema' is accepted on input for backwards compatibility.
 * - strict: Whether the provider must follow the schema strictly (default
 *   FALSE).
 */
class StructuredOutputSchema {

  /**
   * The validator instance.
   */
  private ?ValidatorInterface $validator = NULL;

  /**
   * Constructs a StructuredOutputSchema object.
   *
   * @param string $name
   *   The schema name.
   * @param string|null $description
   *   The schema description.
   * @param bool $strict
   *   Whether the schema is strict.
   * @param array $json_schema
   *   The JSON schema array.
   */
  public function __construct(
    #[Assert\Type('string')]
    #[Assert\Regex(pattern: '/^[a-z0-9_-]+$/', message: 'Schema name must contain only lowercase letters, numbers, underscores, and hyphens.')]
    public string $name = 'json_schema',
    #[Assert\Type(['string', 'null'])]
    public ?string $description = NULL,
    #[Assert\Type('bool')]
    public bool $strict = FALSE,
    #[Assert\Type('array')]
    #[Assert\Collection(
      fields: [
        'properties' => new Assert\Required([new Assert\Type('array')]),
      ],
      allowExtraFields: true,
      allowMissingFields: false
    )]
    public array $json_schema = [],
  ) {
  }

  /**
   * Creates a new DTO instance from an array of values with validation.
   *
   * @param array $values
   *   An associative array of property values.
   *
   * @return static
   *   A new instance of the DTO.
   *
   * @throws \InvalidArgumentException
   *   If validation fails.
   */
  public static function fromArray(array $values): self {
    $schema_content = $values['schema'] ?? $values['json_schema'] ?? $values['jsonSchema'] ?? [];
    $dto = new self(
      name: $values['name'] ?? 'json_schema',
      description: $values['description'] ?? NULL,
      strict: $values['strict'] ?? FALSE,
      json_schema: is_array($schema_content) ? $schema_content : [],
    );

    // Validate the created DTO.
    $validator = Validation::createValidatorBuilder()
      ->enableAttributeMapping()
      ->getValidator();
    $violations = $validator->validate($dto);
    self::checkViolations($violations, 'Invalid schema data: ');
    return $dto;
  }

  /**
   * Gets the validator instance.
   *
   * @return \Symfony\Component\Validator\Validator\ValidatorInterface
   *   The validator instance.
   */
  private function getValidator(): ValidatorInterface {
    if ($this->validator === NULL) {
      $this->validator = Validation::createValidatorBuilder()
        ->enableAttributeMapping()
        ->getValidator();
    }
    return $this->validator;
  }

  /**
   * Checks for validation violations and throws an exception if any exist.
   *
   * @param \Symfony\Component\Validator\ConstraintViolationListInterface $violations
   *   The list of violations.
   * @param string $message_prefix
   *   Optional prefix for the exception message.
   *
   * @throws \InvalidArgumentException
   *   If validation violations exist.
   */
  private static function checkViolations(ConstraintViolationListInterface $violations, string $message_prefix = ''): void {
    if ($violations->count() > 0) {
      throw new \InvalidArgumentException($message_prefix . $violations);
    }
  }

  /**
   * Set the schema name.
   */
  public function setName(string $name): void {
    if ($name !== '') {
      $violations = $this->getValidator()->validatePropertyValue($this, 'name', $name);
      self::checkViolations($violations);
    }
    $this->name = $name !== '' ? $name : 'json_schema';
  }

  /**
   * Get the schema name.
   */
  public function getName(): string {
    return $this->name;
  }

  /**
   * Set the schema description.
   */
  public function setDescription(?string $description): void {
    if ($description !== NULL) {
      $violations = $this->getValidator()->validatePropertyValue($this, 'description', $description);
      self::checkViolations($violations);
    }
    $this->description = $description;
  }

  /**
   * Get the schema description.
   */
  public function getDescription(): ?string {
    return $this->description;
  }

  /**
   * Set strict mode for the schema.
   */
  public function setStrict(bool $strict): void {
    $this->strict = $strict;
  }

  /**
   * Get strict mode for the schema.
   */
  public function isStrict(): bool {
    return $this->strict;
  }

  /**
   * Set the JSON schema array.
   */
  public function setJsonSchema(array $json_schema): void {
    $violations = $this->getValidator()->validatePropertyValue($this, 'json_schema', $json_schema);
    self::checkViolations($violations);
    $this->json_schema = $json_schema;
  }

  /**
   * Get the JSON schema array.
   */
  public function getJsonSchema(): array {
    return $this->json_schema;
  }

  /**
   * Converts the DTO to an associative array.
   *
   * Uses 'schema' as the key for the schema content (documented format used
   * by most providers). Excludes internal properties like validator.
   *
   * @return array
   *   An array with keys: name, description, strict, schema.
   */
  public function toArray(): array {
    return [
      'name' => $this->name,
      'description' => $this->description,
      'strict' => $this->strict,
      'schema' => $this->json_schema,
    ];
  }

}
