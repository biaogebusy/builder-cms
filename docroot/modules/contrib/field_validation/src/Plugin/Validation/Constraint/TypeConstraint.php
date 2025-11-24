<?php

namespace Drupal\field_validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\Constraints\TypeValidator;

/**
 * Type constraint.
 *
 * @Constraint(
 *   id = "Type",
 *   label = @Translation("Type", context = "Validation"),
 * )
 */
class TypeConstraint extends Type {

  /**
   * {@inheritdoc}
   */
  public function validatedBy(): string {
    return TypeValidator::class;
  }

}
