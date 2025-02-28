<?php

namespace Drupal\commerce_cart\EventSubscriber;

use Drupal\commerce_order\Event\OrderEvents;
use Drupal\commerce_order\Event\OrderLabelEvent;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Provides an event subscriber for altering the order label for cart orders.
 */
class OrderLabelSubscriber implements EventSubscriberInterface {

  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      OrderEvents::ORDER_LABEL => ['onLabel'],
    ];
  }

  /**
   * Sets the order label for cart orders.
   *
   * @param \Drupal\commerce_order\Event\OrderLabelEvent $event
   *   The order label event.
   */
  public function onLabel(OrderLabelEvent $event) {
    $order = $event->getOrder();
    if (!empty($order->cart->value)) {
      $event->setLabel($this->t('Cart @id', ['@id' => $order->id()]));
    }
  }

}
