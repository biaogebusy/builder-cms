<?php

namespace Drupal\range\Plugin\Validation\Constraint;

use Drupal\range\RangeItemInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Validates the RangeFromGreaterTo constraint.
 */
class RangeFromGreaterToConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint): void {
    if (!($value instanceof RangeItemInterface)) {
      throw new UnexpectedTypeException($value, 'RangeItemInterface');
    }

    $range = $value->getValue();
    if ($range['from'] > $range['to']) {
      $this->context->addViolation($constraint->message);
    }
  }

}
