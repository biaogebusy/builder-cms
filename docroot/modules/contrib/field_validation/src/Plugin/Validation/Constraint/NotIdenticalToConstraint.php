<?php

namespace Drupal\field_validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraints\NotIdenticalTo;
use Symfony\Component\Validator\Constraints\NotIdenticalToValidator;

/**
 * NotEqualTo constraint.
 *
 * @Constraint(
 *   id = "NotIdenticalTo",
 *   label = @Translation("NotIdenticalTo", context = "Validation"),
 * )
 */
class NotIdenticalToConstraint extends NotIdenticalTo {

  /**
   * Constructs a new constraint instance.
   *
   * @param mixed $options
   *   Options or value to compare against.
   */
  public function __construct($options = NULL) {
    parent::__construct($options);
    if (!is_array($options) || !isset($options['message'])) {
      $this->message = 'This value should not be identical to %compared_value_type %compared_value.';
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validatedBy(): string {
    return NotIdenticalToValidator::class;
  }

}
