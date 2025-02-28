<?php

namespace Drupal\state_machine\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the State constraint.
 *
 * @see \Drupal\state_machine\Plugin\Field\FieldType\StateItemInterface::isValid()
 */
class StateConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    if (!$value->isValid()) {
      $this->context->addViolation($constraint->message, ['@state' => $value->value]);
    }
  }

}
