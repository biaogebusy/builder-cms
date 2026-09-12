<?php

namespace Drupal\commerce_log\EventSubscriber;

use Drupal\commerce_log\LogStorageInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\commerce_checkout\Event\CheckoutEvents;
use Drupal\commerce_order\Event\OrderEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CheckoutEventSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a new CheckoutEventSubscriber object.
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
  public static function getSubscribedEvents(): array {
    return [
      CheckoutEvents::COMPLETION => ['onCheckoutCompletion', -100],
    ];
  }

  /**
   * Creates a log when the customer completes checkout.
   *
   * @param \Drupal\commerce_order\Event\OrderEvent $event
   *   The order event.
   */
  public function onCheckoutCompletion(OrderEvent $event) {
    $order = $event->getOrder();
    $log_storage = $this->entityTypeManager->getStorage('commerce_log');
    assert($log_storage instanceof LogStorageInterface);
    $log_storage->generate($order, 'checkout_complete')->save();
    if ($comments = $order->getCustomerComments()) {
      $log_storage->generate($order, 'commerce_order_from_customer_comment', [
        'comment' => $comments,
      ])->save();
    }
  }

}
