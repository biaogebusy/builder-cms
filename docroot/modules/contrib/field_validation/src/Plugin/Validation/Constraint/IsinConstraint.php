<?php

namespace Drupal\field_validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraints\Isin;

/**
 * Isin constraint.
 *
 * @Constraint(
 *   id = "Isin",
 *   label = @Translation("Isin", context = "Validation"),
 * )
 */
class IsinConstraint extends Isin {

}
