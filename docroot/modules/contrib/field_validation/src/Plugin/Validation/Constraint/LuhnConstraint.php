<?php

namespace Drupal\field_validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraints\Luhn;
use Symfony\Component\Validator\Constraints\LuhnValidator;

/**
 * Luhn constraint.
 *
 * @Constraint(
 *   id = "Luhn",
 *   label = @Translation("Luhn", context = "Validation"),
 * )
 */
class LuhnConstraint extends Luhn {

  /**
   * {@inheritdoc}
   */
  public function validatedBy(): string {
    return LuhnValidator::class;
  }

}
