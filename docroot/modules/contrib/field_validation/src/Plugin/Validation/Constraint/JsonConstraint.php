<?php

namespace Drupal\field_validation\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraints\Json;
use Symfony\Component\Validator\Constraints\JsonValidator;

/**
 * Json constraint.
 *
 * @Constraint(
 *   id = "Json",
 *   label = @Translation("Json", context = "Validation"),
 * )
 */
class JsonConstraint extends Json {

  /**
   * {@inheritdoc}
   */
  public function validatedBy(): string {
    return JsonValidator::class;
  }

}
