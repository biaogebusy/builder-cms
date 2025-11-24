<?php

namespace Drupal\commerce_payment_test\EventSubscriber;

use Drupal\commerce_payment\Event\PaymentEvents;
use Drupal\commerce_payment\Event\RequirePaymentMethodEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class RequirePaymentMethodSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      PaymentEvents::REQUIRE_PAYMENT_METHOD => 'onRequire',
    ];
  }

  /**
   * Overrides default flag value set on payment_information pane.
   *
   * @param \Drupal\commerce_payment\Event\RequirePaymentMethodEvent $event
   *   The event.
   */
  public function onRequire(RequirePaymentMethodEvent $event) {
    $order = $event->getOrder();
    $require = $order->getData('require_payment_method');
    if (is_bool($require)) {
      $event->setRequired($require);
    }
  }

}
