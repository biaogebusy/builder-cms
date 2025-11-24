<?php

namespace Drupal\field_validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraints\CssColor;
use Symfony\Component\Validator\Constraints\CssColorValidator;

/**
 * CssColor constraint.
 *
 * @Constraint(
 *   id = "CssColor",
 *   label = @Translation("CssColor", context = "Validation"),
 * )
 */
class CssColorConstraint extends CssColor {

  /**
   * {@inheritdoc}
   */
  public function validatedBy(): string {
    return CssColorValidator::class;
  }

}
