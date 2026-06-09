<?php

namespace Drupal\ai\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the SimpleTollItemsConstraint constraint.
 */
class SimpleToolItemsConstraintValidator extends ConstraintValidator {

  /**
   * All the classes that implement the FunctionCallInterface.
   *
   * @var array
   */
  protected array $functionCallClasses = [];

  /**
   * Checks if the value is valid.
   */
  public function validate($value, Constraint $constraint) {
    if (!is_array($value)) {
      $this->context->addViolation($constraint->message);
    }
    if (!isset($value['type']) || !isset($value['description'])) {
      $this->context->addViolation($constraint->message);
    }
  }

}
