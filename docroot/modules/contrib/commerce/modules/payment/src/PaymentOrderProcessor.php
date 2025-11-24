<?php

namespace Drupal\commerce_payment;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\OrderProcessorInterface;

/**
 * Recalculates the order's total_paid field.
 */
class PaymentOrderProcessor implements OrderProcessorInterface {

  /**
   * Constructs a new PaymentOrderProcessor instance.
   *
   * @param \Drupal\commerce_payment\PaymentOrderUpdaterInterface $paymentOrderUpdater
   *   The order update manager.
   */
  public function __construct(protected PaymentOrderUpdaterInterface $paymentOrderUpdater) {}

  /**
   * {@inheritdoc}
   */
  public function process(OrderInterface $order) {
    if ($this->paymentOrderUpdater->needsUpdate($order)) {
      $this->paymentOrderUpdater->updateOrder($order);
    }
  }

}
