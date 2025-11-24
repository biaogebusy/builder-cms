<?php

namespace Drupal\field_validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraints\IdenticalTo;
use Symfony\Component\Validator\Constraints\IdenticalToValidator;

/**
 * NotEqualTo constraint.
 *
 * @Constraint(
 *   id = "IdenticalTo",
 *   label = @Translation("IdenticalTo", context = "Validation"),
 * )
 */
class IdenticalToConstraint extends IdenticalTo {

  public string $message = 'This value should be identical to %compared_value_type %compared_value.';
  /**
   * {@inheritdoc}
   */
  public function validatedBy(): string {
    return IdenticalToValidator::class;
  }

}
