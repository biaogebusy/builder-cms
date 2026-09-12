<?php

namespace Drupal\ai_validations\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Ai check constraint.
 */
#[Constraint(
  id: 'AiImagePrompt',
  label: new TranslatableMarkup('AI check', [], ['context' => 'Validation']),
)]
class AiImageConstraint extends SymfonyConstraint {

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
