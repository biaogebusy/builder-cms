<?php

namespace Drupal\commerce_order_test\EventSubscriber;

use Drupal\commerce_order\Event\OrderEvents;
use Drupal\commerce_order\Event\OrderLabelEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Provides a test event subscriber for testing the label alteration.
 */
class OrderLabelSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      OrderEvents::ORDER_LABEL => 'onLabel',
    ];
  }

  /**
   * Sets the order label.
   *
   * @param \Drupal\commerce_order\Event\OrderLabelEvent $event
   *   The order label event.
   */
  public function onLabel(OrderLabelEvent $event) {
    $order = $event->getOrder();
    if ($order->getData('custom_label')) {
      $event->setLabel($order->getData('custom_label'));
    }
  }

}
