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

  public string $message = 'This value should be less than or equal to %compared_value.';
  /**
   * {@inheritdoc}
   */
  public function validatedBy(): string {
    return LessThanOrEqualValidator::class;
  }

}
