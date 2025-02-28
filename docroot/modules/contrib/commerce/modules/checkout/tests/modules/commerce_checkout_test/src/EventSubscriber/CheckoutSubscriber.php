<?php

namespace Drupal\commerce_checkout_test\EventSubscriber;

use Drupal\commerce_checkout\Event\CheckoutCompletionRegisterEvent;
use Drupal\commerce_checkout\Event\CheckoutEvents;
use Drupal\commerce_order\Event\OrderEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CheckoutSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[CheckoutEvents::COMPLETION][] = 'onCompletion';
    $events[CheckoutEvents::COMPLETION_REGISTER][] = 'onRegister';
    return $events;
  }

  /**
   * Stores arbitrary data on the order on checkout completion.
   *
   * @param \Drupal\commerce_order\Event\OrderEvent $event
   *   The event.
   */
  public function onCompletion(OrderEvent $event) {
    $order = $event->getOrder();
    // @see CheckoutOrderTest::testCheckout().
    $order->setData('checkout_completed', TRUE);
  }

  /**
   * Redirects to the user edit page after account creation.
   *
   * @param \Drupal\commerce_checkout\Event\CheckoutCompletionRegisterEvent $event
   *   The event.
   */
  public function onRegister(CheckoutCompletionRegisterEvent $event) {
    /** @var \Drupal\Core\Session\AccountInterface $account */
    $account = $event->getAccount();
    // @see CheckoutOrderTest::testRedirectAfterRegistrationOnCheckout().
    if ($account->getAccountName() == 'bob_redirect') {
      $event->setRedirect('entity.user.edit_form', [
        'user' => $account->id(),
      ]);
    }
  }

}
