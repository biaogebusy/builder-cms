<?php

namespace Drupal\field_validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraints\PositiveOrZero;

/**
 * PositiveOrZero constraint.
 *
 * @Constraint(
 *   id = "PositiveOrZero",
 *   label = @Translation("PositiveOrZero", context = "Validation"),
 * )
 */
class PositiveOrZeroConstraint extends PositiveOrZero {

}
