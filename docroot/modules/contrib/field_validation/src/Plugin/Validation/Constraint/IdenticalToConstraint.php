<?php

namespace Drupal\field_validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraints\IdenticalTo;
use Symfony\Component\Validator\Constraints\IdenticalToValidator;

/**
 * NotEqualTo constraint.
 *
 * @Constraint(
 *   id = "IdenticalTo",
 *   label = @Translation("IdenticalTo", context = "Validation"),
 * )
 */
class IdenticalToConstraint extends IdenticalTo {

  /**
   * Constructs a new constraint instance.
   *
   * @param mixed $options
   *   Options or value to compare against.
   */
  public function __construct($options = NULL) {
    parent::__construct($options);
    if (!is_array($options) || !isset($options['message'])) {
      $this->message = 'This value should be identical to %compared_value_type %compared_value.';
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validatedBy(): string {
    return IdenticalToValidator::class;
  }

}
