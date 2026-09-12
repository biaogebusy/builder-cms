<?php

namespace Drupal\field_validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraints\GreaterThanOrEqual;
use Symfony\Component\Validator\Constraints\GreaterThanOrEqualValidator;

/**
 * GreaterThanOrEqual constraint.
 *
 * @Constraint(
 *   id = "GreaterThanOrEqual",
 *   label = @Translation("GreaterThanOrEqual", context = "Validation"),
 * )
 */
class GreaterThanOrEqualConstraint extends GreaterThanOrEqual {

  /**
   * Constructs a new constraint instance.
   *
   * @param mixed $options
   *   Options or value to compare against.
   */
  public function __construct($options = NULL) {
    parent::__construct($options);
    if (!is_array($options) || !isset($options['message'])) {
      $this->message = 'This value should be greater than or equal to %compared_value.';
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validatedBy(): string {
    return GreaterThanOrEqualValidator::class;
  }

}
