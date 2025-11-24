<?php

namespace Drupal\field_validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraints\Language;
use Symfony\Component\Validator\Constraints\LanguageValidator;

/**
 * Language constraint.
 *
 * @Constraint(
 *   id = "Language",
 *   label = @Translation("Language", context = "Validation"),
 * )
 */
class LanguageConstraint extends Language {

  /**
   * {@inheritdoc}
   */
  public function validatedBy(): string {
    return LanguageValidator::class;
  }

}
