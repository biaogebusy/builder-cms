<?php

namespace Drupal\ai\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/**
 * Validates the ProviderModelDependency constraint.
 */
class ProviderModelDependencyConstraintValidator extends ConstraintValidator {

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint): void {
    if (!$constraint instanceof ProviderModelDependencyConstraint) {
      return;
    }

    // If value is null or not an array, nothing to validate.
    if (!is_array($value)) {
      return;
    }

    // Check if model is set but provider is not.
    if (!empty($value['model']) && empty($value['provider'])) {
      $this->context->addViolation($constraint->message);
    }

    // Check the reverse: if provider is set but model is not.
    if (!empty($value['provider']) && empty($value['model'])) {
      $this->context->addViolation($constraint->message);
    }
  }

}
