<?php

namespace Drupal\commerce_product\Plugin\Field;

use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\Core\Field\EntityReferenceFieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * Computed field item list for the default_variation field.
 */
class ComputedDefaultVariation extends EntityReferenceFieldItemList {
  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue() {
    $product = $this->getEntity();
    assert($product instanceof ProductInterface);
    $this->list[0] = $this->createItem(0, $product->getDefaultVariation());
  }

}
