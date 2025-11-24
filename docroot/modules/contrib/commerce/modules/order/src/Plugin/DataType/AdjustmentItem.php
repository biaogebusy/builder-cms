<?php

namespace Drupal\commerce_order\Plugin\DataType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\Attribute\DataType;
use Drupal\Core\TypedData\TypedData;

/**
 * Defines a data type for adjustment value.
 */
#[DataType(
  id: "adjustment_item",
  label: new TranslatableMarkup('Adjustment item'),
)]
final class AdjustmentItem extends TypedData {

  /**
   * The data value.
   *
   * @var \Drupal\commerce_order\Adjustment
   */
  protected $value;

}
