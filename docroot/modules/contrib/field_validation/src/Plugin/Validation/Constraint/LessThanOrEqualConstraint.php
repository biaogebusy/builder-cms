<?php

namespace Drupal\field_validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraints\LessThanOrEqual;
use Symfony\Component\Validator\Constraints\LessThanOrEqualValidator;

/**
 * LessThanOrEqual constraint.
 *
 * @Constraint(
 *   id = "LessThanOrEqual",
 *   label = @Translation("LessThanOrEqual", context = "Validation"),
 * )
 */
class LessThanOrEqualConstraint extends LessThanOrEqual {

  /**
   * Constructs a new constraint instance.
   *
   * @param mixed $options
   *   Options or value to compare against.
   */
  public function __construct($options = NULL) {
    parent::__construct($options);
    if (!is_array($options) || !isset($options['message'])) {
      $this->message = 'This value should be less than or equal to %compared_value.';
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validatedBy(): string {
    return LessThanOrEqualValidator::class;
  }

}
