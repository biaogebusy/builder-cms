<?php

namespace Drupal\commerce_payment;

use Drupal\Core\DestructableInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_price\Price;

class PaymentOrderUpdater implements PaymentOrderUpdaterInterface, DestructableInterface {

  /**
   * The order IDs that need updating.
   *
   * @var int[]
   */
  protected $updateList = [];

  /**
   * Constructs a new PaymentOrderUpdater object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(protected EntityTypeManagerInterface $entityTypeManager) {}

  /**
   * {@inheritdoc}
   */
  public function requestUpdate(OrderInterface $order) {
    $this->updateList[$order->id()] = $order->id();
  }

  /**
   * {@inheritdoc}
   */
  public function needsUpdate(OrderInterface $order) {
    return !$order->isNew() && isset($this->updateList[$order->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function updateOrders() {
    if (!empty($this->updateList)) {
      /** @var \Drupal\commerce_order\OrderStorage $order_storage */
      $order_storage = $this->entityTypeManager->getStorage('commerce_order');
      foreach ($this->updateList as $order_id) {
        $order = $order_storage->loadForUpdate($order_id);
        if (!$order) {
          continue;
        }
        $this->updateOrder($order, TRUE);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function updateOrder(OrderInterface $order, $save_order = FALSE) {
    $previous_total = $order->getTotalPaid();
    if (!$previous_total) {
      // A NULL total indicates an order that doesn't have any items yet.
      return;
    }
    // The new total is always calculated from scratch, to properly handle
    // orders that were created before the total_paid field was introduced.
    /** @var \Drupal\commerce_payment\PaymentStorageInterface $payment_storage */
    $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');
    $payments = $payment_storage->loadMultipleByOrder($order);
    /** @var \Drupal\commerce_price\Price $new_total */
    $new_total = new Price('0', $previous_total->getCurrencyCode());
    foreach ($payments as $payment) {
      if ($payment->isCompleted()) {
        $new_total = $new_total->add($payment->getBalance());
        // Sync payment_gateway and payment_method fields from the first
        // payment if the payment_method field is empty.
        if ($order->get('payment_method')->isEmpty() && !$payment->get('payment_method')->isEmpty()) {
          $order->set('payment_gateway', $payment->getPaymentGateway());
          $order->set('payment_method', $payment->getPaymentMethod());
        }
      }
    }

    if (!$previous_total->equals($new_total)) {
      $order->setTotalPaid($new_total);
      if ($save_order) {
        // The order should not be refreshed on save as this service
        // may run in a queue or webhook where order processors and
        // conditions will not have access to the user's session.
        // However, the user's session may be required for the refresh to
        // function correctly.
        $order->setRefreshState(OrderInterface::REFRESH_SKIP);
        $order->save();
      }
    }

    unset($this->updateList[$order->id()]);
  }

  /**
   * {@inheritdoc}
   */
  public function destruct() {
    $this->updateOrders();
  }

}
