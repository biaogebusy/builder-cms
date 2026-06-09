<?php

namespace Drupal\ai_validations\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Ai classification check constraint.
 *
 * @Constraint(
 *   id = "AiImageClassification",
 *   label = @Translation("AI Image Classification Check", context = "Validation"),
 * )
 */
class AiImageClassificationConstraint extends Constraint {

  /**
   * The message that will be shown if the constraint is violated.
   *
   * @var string
   */
  public $message = '';

  /**
   * The AI model.
   *
   * @var string
   */
  public $model = '';

  /**
   * The classification tag.
   *
   * @var string
   */
  public $tag = '';

  /**
   * The type of finder.
   *
   * @var string
   */
  public $finder = '';

  /**
   * The minimum confidence to pass.
   *
   * @var float
   */
  public $minimum = 0.0;

  /**
   * The model is not available.
   *
   * @var string
   */
  public $na;

}
