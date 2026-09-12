<?php

declare(strict_types=1);

namespace Drupal\ai\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Validates a JSON Schema structure.
 */
#[Constraint(
  id: 'ValidJsonSchemaStructure',
  label: new TranslatableMarkup('Valid JSON Schema Structure', [], ['context' => 'Validation']),
  type: [
    'array',
    'string',
  ]
)]
class ValidJsonSchemaStructure extends SymfonyConstraint {

  /**
   * The validation message.
   *
   * @var string
   */
  public string $message = 'Invalid JSON Schema: @errors';

  /**
   * Whether to check AI provider-specific constraints.
   *
   * When TRUE, validates constraints like:
   * - All fields must be required
   * - Root must be an object (not anyOf)
   * - No unsupported features.
   *
   * @var bool
   */
  public bool $checkAiConstraints = TRUE;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    mixed $options = NULL,
    ?string $message = NULL,
    ?bool $checkAiConstraints = NULL,
    ?array $groups = NULL,
    mixed $payload = NULL,
  ) {
    parent::__construct($options, $groups, $payload);
    $this->message = $message ?? $this->message;
    $this->checkAiConstraints = $checkAiConstraints ?? $this->checkAiConstraints;
  }

}
