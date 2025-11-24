<?php

namespace Drupal\field_validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraints\Ulid;
use Symfony\Component\Validator\Constraints\UlidValidator;

/**
 * Ulid constraint.
 *
 * @Constraint(
 *   id = "Ulid",
 *   label = @Translation("Ulid", context = "Validation"),
 * )
 */
class UlidConstraint extends Ulid {

  /**
   * {@inheritdoc}
   */
  public function validatedBy(): string {
    return UlidValidator::class;
  }

}
