<?php

namespace Drupal\commerce_checkout\Resolver;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\commerce_order\Entity\OrderInterface;

/**
 * Returns the order type's default checkout flow.
 */
class DefaultCheckoutFlowResolver implements CheckoutFlowResolverInterface {

  /**
   * Constructs a new DefaultCheckoutFlowResolver object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(protected EntityTypeManagerInterface $entityTypeManager) {}

  /**
   * {@inheritdoc}
   */
  public function resolve(OrderInterface $order) {
    /** @var \Drupal\commerce_order\Entity\OrderTypeInterface $order_type */
    $order_type = $this->entityTypeManager->getStorage('commerce_order_type')->load($order->bundle());
    $checkout_flow_id = $order_type->getThirdPartySetting('commerce_checkout', 'checkout_flow', 'default');
    $checkout_flow = $this->entityTypeManager->getStorage('commerce_checkout_flow')->load($checkout_flow_id);
    return $checkout_flow;
  }

}
