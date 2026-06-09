<?php

namespace Drupal\ai_validations\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Ai check constraint.
 *
 * @Constraint(
 *   id = "AiImagePrompt",
 *   label = @Translation("AI check", context = "Validation"),
 * )
 */
class AiImageConstraint extends Constraint {

  /**
   * The prompt.
   *
   * @var string
   */
  public $prompt = NULL;

  /**
   * The message that will be shown if the constraint is violated.
   *
   * @var string
   */
  public $message = '';

  /**
   * The provider.
   *
   * @var string
   */
  public $provider = '';

}
