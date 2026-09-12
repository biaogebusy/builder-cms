<?php

declare(strict_types=1);

namespace Drupal\ai\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Validates against a JSON Schema.
 */
#[Constraint(
  id: 'AiValidator',
  label: new TranslatableMarkup('AI Validator', [], ['context' => 'Validation']),
  type: [
    'string',
  ]
)]
class AiJsonSchemaConstraint extends SymfonyConstraint {

  /**
   * The validation message.
   *
   * @var string
   */
  public string $message = 'The content does not match the required schema: @error';

  /**
   * The schema to validate against.
   *
   * @var string|array|object
   */
  public string|array|object $schema;

}
