<?php

namespace Drupal\field_validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraints\EqualTo;
use Symfony\Component\Validator\Constraints\EqualToValidator;

/**
 * NotEqualTo constraint.
 *
 * @Constraint(
 *   id = "EqualTo",
 *   label = @Translation("EqualTo", context = "Validation"),
 * )
 */
class EqualToConstraint extends EqualTo {

  public string $message = 'This value should be equal to %compared_value.';
  /**
   * {@inheritdoc}
   */
  public function validatedBy(): string {
    return EqualToValidator::class;
  }

}
