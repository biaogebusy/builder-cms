<?php

namespace Drupal\ai\Service\AiProviderValidator;

use Drupal\ai\AiProviderInterface;
use Drupal\ai\Plugin\ProviderProxy;
use Symfony\Component\Validator\ConstraintViolationListInterface;

/**
 * Provides an interface for validators for AI Providers.
 */
interface AiProviderValidatorInterface {

  /**
   * Validates given values against a Provider's config.
   *
   * @param \Drupal\ai\Plugin\ProviderProxy|\Drupal\ai\AiProviderInterface $provider
   *   The AI Provider.
   * @param string $model
   *   The model to use.
   * @param string $operationType
   *   The operation type.
   * @param array<string, mixed> $values
   *   The config values to validate.
   *
   * @return \Symfony\Component\Validator\ConstraintViolationListInterface
   *   Returns a violation list.
   */
  public function validate(ProviderProxy|AiProviderInterface $provider, string $model, string $operationType, array $values): ConstraintViolationListInterface;

  /**
   * Add extra constraints.
   *
   * @param array<string, \Symfony\Component\Validator\Constraint> $constraints
   *   Additional constraints to set, keyed by field.
   *
   * @return \Drupal\ai\Service\AiProviderValidator\AiProviderValidatorInterface
   *   Returns the called object.
   */
  public function addConstraints(array $constraints): AiProviderValidatorInterface;

}
