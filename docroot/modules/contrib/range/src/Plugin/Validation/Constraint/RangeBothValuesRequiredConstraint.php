<?php

namespace Drupal\range\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Defines a both values are required validation constraint.
 *
 * @Constraint(
 *   id = "RangeBothValuesRequired",
 *   label = @Translation("Both range values (FROM and TO) are required.", context = "Validation"),
 * )
 */
class RangeBothValuesRequiredConstraint extends Constraint {

  /**
   * The default violation message.
   *
   * @var string
   */
  public $message = 'Both range values (FROM and TO) are required.';

}
