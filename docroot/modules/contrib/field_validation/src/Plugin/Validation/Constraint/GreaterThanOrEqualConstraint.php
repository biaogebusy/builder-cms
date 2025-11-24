<?php

namespace Drupal\field_validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqualValidator;

/**
 * GreaterThanOrEqual constraint.
 *
 * @Constraint(
 *   id = "GreaterThanOrEqual",
 *   label = @Translation("GreaterThanOrEqual", context = "Validation"),
 * )
 */
class GreaterThanOrEqualConstraint extends GreaterThanOrEqual {

  public string $message = 'This value should be greater than or equal to %compared_value.';
  /**
   * {@inheritdoc}
   */
  public function validatedBy(): string {
    return GreaterThanOrEqualValidator::class;
  }

}
