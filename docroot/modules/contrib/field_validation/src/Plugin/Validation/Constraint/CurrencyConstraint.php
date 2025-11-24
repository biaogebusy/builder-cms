<?php

namespace Drupal\field_validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraints\Currency;
use Symfony\Component\Validator\Constraints\CurrencyValidator;

/**
 * Currency constraint.
 *
 * @Constraint(
 *   id = "SymfonyCurrency",
 *   label = @Translation("Currency", context = "Validation"),
 * )
 */
class CurrencyConstraint extends Currency {

  /**
   * {@inheritdoc}
   */
  public function validatedBy(): string {
    return CurrencyValidator::class;
  }

}
