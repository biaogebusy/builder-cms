<?php

declare(strict_types=1);

namespace Drupal\ai\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Validates a structured output schema.
 */
#[Constraint(
  id: 'ValidStructuredOutputSchema',
  label: new TranslatableMarkup('Valid structured output schema', [], ['context' => 'Validation']),
  type: [
    'array',
  ]
)]
class ValidStructuredOutputSchema extends SymfonyConstraint {

  /**
   * The validation message.
   *
   * @var string
   */
  public string $message = 'Invalid structured output schema: @errors';

  /**
   * Whether to check AI provider-specific constraints.
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
