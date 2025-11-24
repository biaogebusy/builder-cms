<?php

namespace Drupal\commerce_order;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Event\OrderSummaryBuildTotalsEvent;
use Drupal\commerce_order\Event\OrderEvents;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class OrderTotalSummary implements OrderTotalSummaryInterface {

  /**
   * Constructs a new OrderTotalSummary object.
   *
   * @param \Drupal\commerce_order\AdjustmentTransformerInterface $adjustmentTransformer
   *   The adjustment transformer.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   */
  public function __construct(
    protected AdjustmentTransformerInterface $adjustmentTransformer,
    protected EventDispatcherInterface $eventDispatcher,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function buildTotals(OrderInterface $order) {
    $adjustments = $order->collectAdjustments();
    $adjustments = $this->adjustmentTransformer->processAdjustments($adjustments);
    // Included adjustments are not displayed to the customer, they
    // exist to allow the developer to know what the price is made of.
    // The one exception is taxes, which need to be shown for legal reasons.
    $adjustments = array_filter($adjustments, function (Adjustment $adjustment) {
      return $adjustment->getType() == 'tax' || !$adjustment->isIncluded();
    });
    // Convert the adjustments to arrays.
    $adjustments = array_map(function (Adjustment $adjustment) {
      return $adjustment->toArray();
    }, $adjustments);
    // Provide the "total" key for backwards compatibility reasons.
    foreach ($adjustments as $index => $adjustment) {
      $adjustments[$index]['total'] = $adjustments[$index]['amount'];
    }
    // Create the totals array:
    $totals = [
      'subtotal' => $order->getSubtotalPrice(),
      'adjustments' => $adjustments,
      'total' => $order->getTotalPrice(),
    ];
    // Allow modifying the totals array built above via event subscribers.
    $event = new OrderSummaryBuildTotalsEvent($order, $totals);
    $this->eventDispatcher->dispatch($event, OrderEvents::ORDER_SUMMARY_BUILD_TOTALS);

    return $event->getTotals();
  }

}
