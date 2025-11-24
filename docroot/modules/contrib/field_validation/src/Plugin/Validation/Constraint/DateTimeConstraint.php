<?php

namespace Drupal\field_validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraints\DateTime;
use Symfony\Component\Validator\Constraints\DateTimeValidator;

/**
 * DateTime constraint.
 *
 * @Constraint(
 *   id = "DateTime",
 *   label = @Translation("DateTime", context = "Validation"),
 * )
 */
class DateTimeConstraint extends DateTime {

  /**
   * {@inheritdoc}
   */
  public function validatedBy(): string {
    return DateTimeValidator::class;
  }

}
