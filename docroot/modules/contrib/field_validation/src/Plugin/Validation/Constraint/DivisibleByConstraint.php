<?php

namespace Drupal\field_validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraints\DivisibleBy;
use Symfony\Component\Validator\Constraints\DivisibleByValidator;

/**
 * DivisibleBy constraint.
 *
 * @Constraint(
 *   id = "DivisibleBy",
 *   label = @Translation("DivisibleBy", context = "Validation"),
 * )
 */
class DivisibleByConstraint extends DivisibleBy {

  /**
   * Constructs a new constraint instance.
   *
   * @param mixed $options
   *   Options or value to compare against.
   */
  public function __construct($options = NULL) {
    parent::__construct($options);
    if (!is_array($options) || !isset($options['message'])) {
      $this->message = 'This value should be a multiple of %compared_value.';
    }
  }

  /**
   * {@inheritdoc}
   */
  public function validatedBy(): string {
    return DivisibleByValidator::class;
  }

}
