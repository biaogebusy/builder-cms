<?php

namespace Drupal\field_validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraints\NoSuspiciousCharacters;
use Symfony\Component\Validator\Constraints\NoSuspiciousCharactersValidator;

/**
 * NoSuspiciousCharacters constraint.
 *
 * @Constraint(
 *   id = "NoSuspiciousCharacters",
 *   label = @Translation("NoSuspiciousCharacters", context = "Validation"),
 * )
 */
class NoSuspiciousCharactersConstraint extends NoSuspiciousCharacters {

  /**
   * {@inheritdoc}
   */
  public function validatedBy(): string {
    return NoSuspiciousCharactersValidator::class;
  }

}
