<?php

namespace Drupal\field_validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraints\NotEqualTo;
use Symfony\Component\Validator\Constraints\NotEqualToValidator;

/**
 * NotEqualTo constraint.
 *
 * @Constraint(
 *   id = "NotEqualTo",
 *   label = @Translation("NotEqualTo", context = "Validation"),
 * )
 */
class NotEqualToConstraint extends NotEqualTo {

  public string $message = 'This value should not be equal to %compared_value.';
  /**
   * {@inheritdoc}
   */
  public function validatedBy(): string {
    return NotEqualToValidator::class;
  }

}
