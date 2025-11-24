<?php

namespace Drupal\field_validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraints\Positive;

/**
 * Positive constraint.
 *
 * @Constraint(
 *   id = "Positive",
 *   label = @Translation("Positive", context = "Validation"),
 * )
 */
class PositiveConstraint extends Positive {

}
