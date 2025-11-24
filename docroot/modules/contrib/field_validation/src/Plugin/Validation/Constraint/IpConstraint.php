<?php

namespace Drupal\field_validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraints\Ip;
use Symfony\Component\Validator\Constraints\IpValidator;

/**
 * Ip constraint.
 *
 * @Constraint(
 *   id = "Ip",
 *   label = @Translation("Ip", context = "Validation"),
 * )
 */
class IpConstraint extends Ip {

  /**
   * {@inheritdoc}
   */
  public function validatedBy(): string {
    return IpValidator::class;
  }

}
