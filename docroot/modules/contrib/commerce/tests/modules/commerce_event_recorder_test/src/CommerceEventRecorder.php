<?php

namespace Drupal\commerce_event_recorder_test;

use Drupal\Core\State\StateInterface;
use Drupal\commerce_payment\Event\FailedPaymentEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class CommerceEventRecorder implements EventSubscriberInterface {

  public const STATE_KEY_PREFIX = 'CommerceEventRecorder.';

  /**
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   */
  public function __construct(private StateInterface $state) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    // Using strings here rather than class constants so this module has no
    // code dependencies.
    $events['commerce_payment.commerce_payment.failure'][] = ['onPaymentFailure'];
    return $events;
  }

  /**
   * @param \Drupal\commerce_payment\Event\FailedPaymentEvent $event
   *   The failed payment event.
   */
  public function onPaymentFailure(FailedPaymentEvent $event): void {
    $key = self::STATE_KEY_PREFIX . 'onPaymentFailure';
    $records = $this->state->get($key, []);
    $records[] = [
      'order_id' => $event->getOrder()->id(),
      'payment_type' => (string) $event->getPayment()?->getType()->getLabel(),
      'payment_gateway' => (string) $event->getPaymentGateway()->label(),
      'payment_method' => (string) $event->getPaymentMethod()?->label(),
    ];
    $this->state->set($key, $records);
  }

}
