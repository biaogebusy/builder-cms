<?php

declare(strict_types=1);

namespace Drupal\ai\Plugin\Validation\Constraint;

use Drupal\ai\Service\AiValidatorInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Validates the ValidJsonSchemaStructure constraint.
 */
class ValidJsonSchemaStructureValidator extends ConstraintValidator implements ContainerInjectionInterface {

  /**
   * Constructs the validator.
   *
   * @param \Drupal\ai\Service\AiValidatorInterface $aiValidator
   *   The AI validator service.
   */
  public function __construct(
    protected AiValidatorInterface $aiValidator,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self($container->get('ai.validator'));
  }

  /**
   * {@inheritdoc}
   */
  public function validate(mixed $value, Constraint $constraint): void {
    if (!$constraint instanceof ValidJsonSchemaStructure) {
      throw new UnexpectedTypeException($constraint, ValidJsonSchemaStructure::class);
    }

    if ($value === NULL || $value === [] || $value === '') {
      return;
    }

    // Validate using the AI validator service.
    $errors = $this->aiValidator->validateJsonSchemaStructure(
      $value,
      $constraint->checkAiConstraints
    );

    if (!empty($errors)) {
      $error_message = implode(' ', $errors);
      $this->context->buildViolation($constraint->message)
        ->setParameter('@errors', $error_message)
        ->addViolation();
    }
  }

}
