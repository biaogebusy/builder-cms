<?php

namespace Drupal\range\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Drupal\range\RangeItemInterface;

/**
 * Validates the RangeBothValuesRequired constraint.
 */
class RangeBothValuesRequiredConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint) {
    if (!($value instanceof RangeItemInterface)) {
      throw new UnexpectedTypeException($value, 'RangeItemInterface');
    }

    $range = $value->getValue();
    if (empty($range['from']) && (string) $range['from'] !== '0' ||
        empty($range['to']) && (string) $range['to'] !== '0') {
      $this->context->addViolation($constraint->message);
    }
  }

}
