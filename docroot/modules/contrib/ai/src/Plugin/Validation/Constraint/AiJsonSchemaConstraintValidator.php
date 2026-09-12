<?php

declare(strict_types=1);

namespace Drupal\ai\Plugin\Validation\Constraint;

use Drupal\ai\Service\AiValidatorInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;

/**
 * Validates the AiJsonSchemaConstraint constraint.
 */
class AiJsonSchemaConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * The AI validator service.
   *
   * @var \Drupal\ai\Service\AiValidatorInterface
   */
  protected AiValidatorInterface $aiValidator;

  /**
   * Constructs the validator.
   *
   * @param \Drupal\ai\Service\AiValidatorInterface $ai_validator
   *   The AI validator service.
   */
  public function __construct(AiValidatorInterface $ai_validator) {
    $this->aiValidator = $ai_validator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('ai.validator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function validate($value, Constraint $constraint): void {
    if (!isset($value) || $value === '') {
      return;
    }

    if (!$constraint instanceof AiJsonSchemaConstraint) {
      return;
    }

    $errors = $this->aiValidator->validateJsonSchema($value, $constraint->schema);

    if (!empty($errors)) {
      foreach ($errors as $error) {
        $this->context->buildViolation($constraint->message)
          ->setParameter('@error', $error)
          ->addViolation();
      }
    }
  }

}
