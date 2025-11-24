<?php

namespace Drupal\field_validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraints\Expression;
use Symfony\Component\Validator\Constraints\ExpressionValidator;

/**
 * Expression constraint.
 *
 * @Constraint(
 *   id = "Expression",
 *   label = @Translation("Expression", context = "Validation"),
 * )
 */
class ExpressionConstraint extends Expression {

  /**
   * {@inheritdoc}
   */
  public function validatedBy(): string {
    return ExpressionValidator::class;
  }

}
