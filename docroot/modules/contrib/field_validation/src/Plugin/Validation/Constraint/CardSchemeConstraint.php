<?php

namespace Drupal\field_validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraints\CardScheme;
use Symfony\Component\Validator\Constraints\CardSchemeValidator;

/**
 * CardScheme constraint.
 *
 * @Constraint(
 *   id = "CardScheme",
 *   label = @Translation("CardScheme", context = "Validation"),
 * )
 */
class CardSchemeConstraint extends CardScheme {

  /**
   * {@inheritdoc}
   */
  public function validatedBy(): string {
    return CardSchemeValidator::class;
  }

}
