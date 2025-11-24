<?php

namespace Drupal\field_validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraints\Negative;

/**
 * Negative constraint.
 *
 * @Constraint(
 *   id = "Negative",
 *   label = @Translation("Negative", context = "Validation"),
 * )
 */
class NegativeConstraint extends Negative {

}
