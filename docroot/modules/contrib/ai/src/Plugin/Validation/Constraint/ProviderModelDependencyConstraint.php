<?php

namespace Drupal\ai\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Validates that provider_id is set when model_id is set.
 */
#[Constraint(
  id: 'ProviderModelDependency',
  label: new TranslatableMarkup('Provider Model Dependency', options: ['context' => 'Validation']),
)]
class ProviderModelDependencyConstraint extends SymfonyConstraint {

  /**
   * The error message.
   *
   * @var string
   */
  public string $message = 'The provider_id and model_id has to be set together, not just one.';

}
