<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_reference\Plugin\Validation\Constraint;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\node\NodeInterface;
use Drupal\xinshi_ai_reference\TaskReferenceGuard;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;

/** Reuses the same guard for validation and direct entity-save callers. */
final class TaskReferenceConstraintValidator extends ConstraintValidator implements ContainerInjectionInterface {

  public function __construct(private readonly TaskReferenceGuard $guard) {}

  public static function create(ContainerInterface $container): static {
    return new static($container->get('xinshi_ai_reference.guard'));
  }

  public function validate($value, Constraint $constraint): void {
    if (!$value instanceof NodeInterface) {
      return;
    }
    try {
      $this->guard->validate($value);
    }
    catch (UnprocessableEntityHttpException) {
      $this->context->addViolation('Invalid protected task reference.');
    }
  }

}
