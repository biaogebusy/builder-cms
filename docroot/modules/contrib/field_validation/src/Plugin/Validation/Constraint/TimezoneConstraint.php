<?php

namespace Drupal\field_validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraints\Timezone;
use Symfony\Component\Validator\Constraints\TimezoneValidator;

/**
 * Timezone constraint.
 *
 * @Constraint(
 *   id = "Timezone",
 *   label = @Translation("Timezone", context = "Validation"),
 * )
 */
class TimezoneConstraint extends Timezone {

  /**
   * {@inheritdoc}
   */
  public function validatedBy(): string {
    return TimezoneValidator::class;
  }

}
