<?php

namespace Drupal\field_validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraints\IsFalse;
use Symfony\Component\Validator\Constraints\IsFalseValidator;

/**
 * False constraint.
 *
 * @Constraint(
 *   id = "False",
 *   label = @Translation("False", context = "Validation"),
 * )
 */
class IsFalseConstraint extends IsFalse {

  /**
   * {@inheritdoc}
   */
  public function validatedBy(): string {
    return IsFalseValidator::class;
  }

}
