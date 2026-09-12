<?php

namespace Drupal\field_validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraints\LessThan;
use Symfony\Component\Validator\Constraints\LessThanValidator;

/**
 * LessThan constraint.
 *
 * @Constraint(
 *   id = "LessThan",
 *   label = @Translation("LessThan", context = "Validation"),
 * )
 */
class LessThanConstraint extends LessThan {

  /**
   * Constructs a new constraint instance.
   *
   * @param mixed $options
   *   Options or value to compare against.
   */
  public function __construct($options = NULL) {
    parent::__construct($options);
    if (!is_array($options) || !isset($options['message'])) {
      $this->message = 'This value should be less than %compared_value.';
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validatedBy(): string {
    return LessThanValidator::class;
  }

}
