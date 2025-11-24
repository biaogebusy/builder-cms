<?php

namespace Drupal\field_validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraints\Iban;
use Symfony\Component\Validator\Constraints\IbanValidator;

/**
 * Iban constraint.
 *
 * @Constraint(
 *   id = "Iban",
 *   label = @Translation("Iban", context = "Validation"),
 * )
 */
class IbanConstraint extends Iban {

  /**
   * {@inheritdoc}
   */
  public function validatedBy(): string {
    return IbanValidator::class;
  }

}
