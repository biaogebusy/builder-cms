<?php

namespace Drupal\field_validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraints\GreaterThan;
use Symfony\Component\Validator\Constraints\GreaterThanValidator;

/**
 * GreaterThan constraint.
 *
 * @Constraint(
 *   id = "GreaterThan",
 *   label = @Translation("GreaterThan", context = "Validation"),
 * )
 */
class GreaterThanConstraint extends GreaterThan {

  public string $message = 'This value should be greater than %compared_value.';
  /**
   * {@inheritdoc}
   */
  public function validatedBy(): string {
    return GreaterThanValidator::class;
  }

}
