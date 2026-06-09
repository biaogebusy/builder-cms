<?php

declare(strict_types=1);

namespace Drupal\ai\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Validates the FixedValue constraint.
 */
class FixedValueValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    if (!$constraint instanceof FixedValue) {
      throw new UnexpectedTypeException($constraint, FixedValue::class);
    }

    if ($value !== $constraint->value) {
      $this->context->addViolation($constraint->message, ['%value' => (string) $constraint->value]);
    }
  }

}
