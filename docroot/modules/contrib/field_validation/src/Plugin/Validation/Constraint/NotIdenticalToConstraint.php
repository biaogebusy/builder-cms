<?php

namespace Drupal\field_validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraints\NotIdenticalTo;
use Symfony\Component\Validator\Constraints\NotIdenticalToValidator;

/**
 * NotEqualTo constraint.
 *
 * @Constraint(
 *   id = "NotIdenticalTo",
 *   label = @Translation("NotIdenticalTo", context = "Validation"),
 * )
 */
class NotIdenticalToConstraint extends NotIdenticalTo {

  public string $message = 'This value should not be identical to %compared_value_type %compared_value.';
  /**
   * {@inheritdoc}
   */
  public function validatedBy(): string {
    return NotIdenticalToValidator::class;
  }

}
