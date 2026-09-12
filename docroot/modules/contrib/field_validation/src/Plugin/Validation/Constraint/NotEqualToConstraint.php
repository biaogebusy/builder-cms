<?php

namespace Drupal\field_validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraints\NotEqualTo;
use Symfony\Component\Validator\Constraints\NotEqualToValidator;

/**
 * NotEqualTo constraint.
 *
 * @Constraint(
 *   id = "NotEqualTo",
 *   label = @Translation("NotEqualTo", context = "Validation"),
 * )
 */
class NotEqualToConstraint extends NotEqualTo {

  /**
   * Constructs a new constraint instance.
   *
   * @param mixed $options
   *   Options or value to compare against.
   */
  public function __construct($options = NULL) {
    parent::__construct($options);
    if (!is_array($options) || !isset($options['message'])) {
      $this->message = 'This value should not be equal to %compared_value.';
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validatedBy(): string {
    return NotEqualToValidator::class;
  }

}
