<?php

namespace Drupal\field_validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraints\Hostname;
use Symfony\Component\Validator\Constraints\HostnameValidator;

/**
 * Hostname constraint.
 *
 * @Constraint(
 *   id = "Hostname",
 *   label = @Translation("Hostname", context = "Validation"),
 * )
 */
class HostnameConstraint extends Hostname {

  /**
   * {@inheritdoc}
   */
  public function validatedBy(): string {
    return HostnameValidator::class;
  }

}
