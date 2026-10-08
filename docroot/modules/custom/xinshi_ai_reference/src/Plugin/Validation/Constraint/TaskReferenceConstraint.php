<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_reference\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/** Validates protected locators before REST persistence, yielding a normal 422 violation. */
#[Constraint(id: 'XinshiTaskReference', label: new TranslatableMarkup('Protected task reference'))]
final class TaskReferenceConstraint extends SymfonyConstraint {}
