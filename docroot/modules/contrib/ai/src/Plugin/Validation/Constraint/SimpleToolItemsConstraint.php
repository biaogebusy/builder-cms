<?php

namespace Drupal\ai\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Custom constraint to add lists of items in tool calling.
 */
#[Constraint(
  id: 'SimpleToolItems',
  label: new TranslatableMarkup('Tool Items', [], ['context' => 'Validation'])
)]
class SimpleToolItemsConstraint extends SymfonyConstraint {

  /**
   * The error message.
   *
   * @var string
   */
  public $message = "The value '%value' has to be an array that has a key type and description.";

}
