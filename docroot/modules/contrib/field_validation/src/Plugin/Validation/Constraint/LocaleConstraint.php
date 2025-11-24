<?php

namespace Drupal\field_validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraints\Locale;
use Symfony\Component\Validator\Constraints\LocaleValidator;

/**
 * Locale constraint.
 *
 * @Constraint(
 *   id = "Locale",
 *   label = @Translation("Locale", context = "Validation"),
 * )
 */
class LocaleConstraint extends Locale {

  /**
   * {@inheritdoc}
   */
  public function validatedBy(): string {
    return LocaleValidator::class;
  }

}
