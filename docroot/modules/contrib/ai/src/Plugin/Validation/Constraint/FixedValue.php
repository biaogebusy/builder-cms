<?php

declare(strict_types=1);

namespace Drupal\ai\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint as ConstraintAttribute;
use Symfony\Component\Validator\Constraint;

/**
 * Custom constraint to set constants in tool calling.
 */
#[ConstraintAttribute(
  id: 'FixedValue',
  label: new TranslatableMarkup('Fixed value', [], ['context' => 'Validation']),
)]
class FixedValue extends Constraint {

  /**
   * The fixed value that must be used.
   *
   * @var mixed
   */
  public $value;

  /**
   * The message to show when validation fails.
   *
   * @var string
   */
  public $message = 'The value must be %value';

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
