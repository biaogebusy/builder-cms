<?php

namespace Drupal\range\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Defines a FROM value is higher than TO value validation constraint.
 *
 * @Constraint(
 *   id = "RangeFromGreaterTo",
 *   label = @Translation("The FROM value is greater than the TO value.", context = "Validation"),
 * )
 */
class RangeFromGreaterToConstraint extends Constraint {

  /**
   * The default violation message.
   *
   * @var string
   */
  public $message = 'The FROM value is higher than the TO value.';

}
