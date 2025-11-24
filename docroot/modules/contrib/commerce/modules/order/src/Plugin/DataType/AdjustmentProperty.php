<?php

namespace Drupal\commerce_order\Plugin\DataType;

use Drupal\commerce_order\Adjustment;
use Drupal\commerce_order\Plugin\Field\FieldType\AdjustmentItem;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\Attribute\DataType;
use Drupal\Core\TypedData\TypedData;

/**
 * Defines a data type for adjustments.
 */
#[DataType(
  id: "adjustment_property",
  label: new TranslatableMarkup('Adjustment property'),
)]
final class AdjustmentProperty extends TypedData {

  /**
   * The data value.
   *
   * @var \Drupal\commerce_order\Adjustment
   */
  protected $value;

  /**
   * {@inheritdoc}
   */
  public function setValue($value, $notify = TRUE): void {
    $parent = $this->getParent();
    if (!($parent instanceof AdjustmentItem) || $parent->isEmpty()) {
      return;
    }
    $parent_values = $parent->getValue();
    $parent_value = reset($parent_values);
    if (!($parent_value instanceof Adjustment)) {
      return;
    }
    $this->value = $parent_value->toArray()[$this->getName()] ?? NULL;
  }

}
