<?php

namespace Drupal\commerce_order\Resolver;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;

/**
 * Returns the order type, based on order item type configuration.
 */
class DefaultOrderTypeResolver implements OrderTypeResolverInterface {

  /**
   * Constructs a new DefaultOrderTypeResolver object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function resolve(OrderItemInterface $order_item) {
    /** @var \Drupal\commerce_order\Entity\OrderItemTypeInterface $order_item_type */
    $order_item_type = $this->entityTypeManager->getStorage('commerce_order_item_type')->load($order_item->bundle());

    return $order_item_type->getOrderTypeId();
  }

}
