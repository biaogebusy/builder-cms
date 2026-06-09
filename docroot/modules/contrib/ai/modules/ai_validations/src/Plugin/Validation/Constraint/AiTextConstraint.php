<?php

namespace Drupal\ai_validations\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Ai check constraint.
 *
 * @Constraint(
 *   id = "AiTextPrompt",
 *   label = @Translation("AI check", context = "Validation"),
 * )
 */
class AiTextConstraint extends Constraint {

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
