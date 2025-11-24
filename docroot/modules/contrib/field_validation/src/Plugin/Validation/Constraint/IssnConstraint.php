<?php

namespace Drupal\field_validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraints\Issn;
use Symfony\Component\Validator\Constraints\IssnValidator;

/**
 * Issn constraint.
 *
 * @Constraint(
 *   id = "Issn",
 *   label = @Translation("Issn", context = "Validation"),
 * )
 */
class IssnConstraint extends Issn {

  /**
   * {@inheritdoc}
   */
  public function validatedBy(): string {
    return IssnValidator::class;
  }

}
