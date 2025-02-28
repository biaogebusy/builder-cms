<?php

namespace Drupal\commerce_order;

use Drupal\Core\Field\FieldItemList;
use Drupal\Core\TypedData\ComputedItemListTrait;

/**
 * Computed field item list for the 'balance' commerce_order field.
 */
class OrderBalanceFieldItemList extends FieldItemList {

  use ComputedItemListTrait;

  /**
   * {@inheritdoc}
   */
  protected function computeValue() {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $this->getEntity();
    $this->list[0] = $this->createItem(0, $order->getBalance());
  }

}
