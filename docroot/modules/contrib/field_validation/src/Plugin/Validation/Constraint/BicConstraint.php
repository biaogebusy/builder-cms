<?php

namespace Drupal\field_validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraints\Bic;
use Symfony\Component\Validator\Constraints\BicValidator;

/**
 * Bic constraint.
 *
 * @Constraint(
 *   id = "Bic",
 *   label = @Translation("Bic", context = "Validation"),
 * )
 */
class BicConstraint extends Bic {

  /**
   * {@inheritdoc}
   */
  public function validatedBy(): string {
    return BicValidator::class;
  }

}
