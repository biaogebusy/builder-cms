<?php

namespace Drupal\ai_validations\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Ai check constraint.
 */
#[Constraint(
  id: 'AiTextPrompt',
  label: new TranslatableMarkup('AI check', [], ['context' => 'Validation']),
)]
class AiTextConstraint extends SymfonyConstraint {

  /**
   * Prompt.
   *
   * @var string
   */
  public $prompt = NULL;

  /**
   * Message that will be shown if the constraint is violated.
   *
   * @var string
   */
  public $message = '';

  /**
   * Provider.
   *
   * @var string
   */
  public $provider = '';

}
