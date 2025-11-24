<?php

namespace Drupal\field_validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraints\DivisibleBy;
use Symfony\Component\Validator\Constraints\DivisibleByValidator;

/**
 * DivisibleBy constraint.
 *
 * @Constraint(
 *   id = "DivisibleBy",
 *   label = @Translation("DivisibleBy", context = "Validation"),
 * )
 */
class DivisibleByConstraint extends DivisibleBy {

  public string $message = 'This value should be a multiple of %compared_value.';
  /**
   * {@inheritdoc}
   */
  public function validatedBy(): string {
    return DivisibleByValidator::class;
  }

}
