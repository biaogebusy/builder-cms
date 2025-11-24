<?php

namespace Drupal\field_validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\IsTrueValidator;

/**
 * True constraint.
 *
 * @Constraint(
 *   id = "True",
 *   label = @Translation("True", context = "Validation"),
 * )
 */
class IsTrueConstraint extends IsTrue {

  /**
   * {@inheritdoc}
   */
  public function validatedBy(): string {
    return IsTrueValidator::class;
  }

}
