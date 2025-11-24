<?php

namespace Drupal\field_validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraints\Time;
use Symfony\Component\Validator\Constraints\TimeValidator;

/**
 * Time constraint.
 *
 * @Constraint(
 *   id = "Time",
 *   label = @Translation("Time", context = "Validation"),
 * )
 */
class TimeConstraint extends Time {

  /**
   * {@inheritdoc}
   */
  public function validatedBy(): string {
    return TimeValidator::class;
  }

}
