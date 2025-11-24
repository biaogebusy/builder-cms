<?php

namespace Drupal\field_validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraints\NegativeOrZero;

/**
 * NegativeOrZero constraint.
 *
 * @Constraint(
 *   id = "NegativeOrZero",
 *   label = @Translation("NegativeOrZero", context = "Validation"),
 * )
 */
class NegativeOrZeroConstraint extends NegativeOrZero {

}
