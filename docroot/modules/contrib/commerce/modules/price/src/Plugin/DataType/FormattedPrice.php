<?php

namespace Drupal\commerce_price\Plugin\DataType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\TypedData\Attribute\DataType;
use Drupal\Core\TypedData\Plugin\DataType\StringData;
use Drupal\commerce_price\Plugin\Field\FieldType\PriceItem;

/**
 * Defines a data type for formatted prices.
 */
#[DataType(
  id: "formatted_price",
  label: new TranslatableMarkup('Formatted price'),
)]
class FormattedPrice extends StringData {

  /**
   * {@inheritdoc}
   */
  public function getValue() {
    $parent = $this->getParent();
    assert($parent instanceof PriceItem);
    $formatted_price = NULL;
    if (!$parent->isEmpty()) {
      try {
        $price = $parent->toPrice();
        $currency_formatter = \Drupal::service('commerce_price.currency_formatter');
        $formatted_price = $currency_formatter->format($price->getNumber(), $price->getCurrencyCode());
      }
      catch (\InvalidArgumentException $exception) {

      }
    }

    return $formatted_price;
  }

}
