<?php

namespace Drupal\ai\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint as ConstraintAttribute;
use Symfony\Component\Validator\Constraint;

/**
 * Custom constraint to add lists of items in tool calling.
 */
#[ConstraintAttribute(
  id: 'ComplexToolItems',
  label: new TranslatableMarkup('Tool Items', [], ['context' => 'Validation']),
  // We might need to add some types there.
  type: [
    'list',
    'map',
  ],
)]
class ComplexToolItemsConstraint extends Constraint {

  /**
   * The object value that must be used.
   *
   * @var mixed
   */
  public $value;

  /**
   * The error message.
   *
   * @var string
   */
  public $message = "The value '%value' has to be an class or array of classes that implements the FunctionCallInterface or the output value.";

  /**
   * {@inheritdoc}
   */
  public function __construct($options = NULL) {
    parent::__construct($options);
    // Allow for a compact notation where the value is directly provided.
    if (isset($options) && !is_array($options) && !($options instanceof \Traversable)) {
      $this->value = $options;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultOption(): string {
    return 'value';
  }

  /**
   * {@inheritdoc}
   */
  public function getRequiredOptions(): array {
    return ['value'];
  }

}
