<?php

namespace Drupal\field_validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraints\Date;
use Symfony\Component\Validator\Constraints\DateValidator;

/**
 * Date constraint.
 *
 * @Constraint(
 *   id = "Date",
 *   label = @Translation("Date", context = "Validation"),
 * )
 */
class DateConstraint extends Date {

  /**
   * {@inheritdoc}
   */
  public function validatedBy(): string {
    return DateValidator::class;
  }

}
