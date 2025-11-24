<?php

namespace Drupal\field_validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraints\Isbn;
use Symfony\Component\Validator\Constraints\IsbnValidator;

/**
 * Isbn constraint.
 *
 * @Constraint(
 *   id = "Isbn",
 *   label = @Translation("Isbn", context = "Validation"),
 * )
 */
class IsbnConstraint extends Isbn {

  /**
   * {@inheritdoc}
   */
  public function validatedBy(): string {
    return IsbnValidator::class;
  }

}
