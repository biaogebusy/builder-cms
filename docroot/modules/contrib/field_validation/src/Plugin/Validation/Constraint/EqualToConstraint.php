<?php

namespace Drupal\field_validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraints\EqualTo;
use Symfony\Component\Validator\Constraints\EqualToValidator;

/**
 * NotEqualTo constraint.
 *
 * @Constraint(
 *   id = "EqualTo",
 *   label = @Translation("EqualTo", context = "Validation"),
 * )
 */
class EqualToConstraint extends EqualTo {

  /**
   * Constructs a new constraint instance.
   *
   * @param mixed $options
   *   Options or value to compare against.
   */
  public function __construct($options = NULL) {
    parent::__construct($options);
    if (!is_array($options) || !isset($options['message'])) {
      $this->message = 'This value should be equal to %compared_value.';
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validatedBy(): string {
    return EqualToValidator::class;
  }

}
