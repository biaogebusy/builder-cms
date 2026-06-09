<?php

namespace Drupal\ai\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint as ConstraintAttribute;
use Symfony\Component\Validator\Constraint;

/**
 * Custom constraint to add lists of items in tool calling.
 */
#[ConstraintAttribute(
  id: 'SimpleToolItems',
  label: new TranslatableMarkup('Tool Items', [], ['context' => 'Validation'])
)]
class SimpleToolItemsConstraint extends Constraint {

  /**
   * The error message.
   *
   * @var string
   */
  public $message = "The value '%value' has to be an array that has a key type and description.";

}
