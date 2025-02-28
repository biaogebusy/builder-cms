<?php

namespace Drupal\commerce_checkout\EventSubscriber;

use Drupal\commerce_checkout\Entity\CheckoutFlowInterface;
use Drupal\commerce_checkout\Event\CheckoutEvents;
use Drupal\commerce_order\Event\OrderEvent;
use Drupal\commerce_order\OrderAssignmentInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Password\PasswordGeneratorInterface;
use Drupal\user\UserInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class GuestCheckoutCompletionSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a new GuestCheckoutCompletionSubscriber object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\commerce_order\OrderAssignmentInterface $orderAssignment
   *   The order assignment.
   * @param \Drupal\Core\Password\PasswordGeneratorInterface $passwordGenerator
   *   The password generator.
   */
  public function __construct(protected EntityTypeManagerInterface $entityTypeManager, protected OrderAssignmentInterface $orderAssignment, protected PasswordGeneratorInterface $passwordGenerator) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      CheckoutEvents::COMPLETION => ['onCompletion'],
    ];
  }

  /**
   * Handles guest checkout completion.
   *
   * Based on the following checkout flow settings:
   * - guest_new_account: creates new guest account.
   * - guest_order_assign: assigns the order to an existing user account.
   *
   * @param \Drupal\commerce_order\Event\OrderEvent $event
   *   The order event.
   */
  public function onCompletion(OrderEvent $event) {
    $order = $event->getOrder();
    $customer = $order->getCustomer();
    if ($customer->isAuthenticated() ||
      $order->get('checkout_flow')->isEmpty() ||
      empty($order->getEmail())) {
      return;
    }

    $checkout_flow = $order->get('checkout_flow')->entity;
    assert($checkout_flow instanceof CheckoutFlowInterface);
    $checkout_flow_plugin = $checkout_flow->getPlugin();
    $configuration = $checkout_flow_plugin->getConfiguration();
    $guest_new_account = $configuration['guest_new_account'] ?? FALSE;
    $guest_order_assign = $configuration['guest_order_assign'] ?? FALSE;
    if (!$guest_new_account && !$guest_order_assign) {
      return;
    }

    $mail = $order->getEmail();
    /** @var \Drupal\user\UserStorageInterface $user_storage */
    $user_storage = $this->entityTypeManager->getStorage('user');
    $existing_user = $user_storage->loadByProperties([
      'mail' => $mail,
    ]);
    $existing_user = reset($existing_user);
    if ($existing_user === FALSE && $guest_new_account) {
      // Make a new account.
      $created_user = $user_storage->create([
        'name' => $mail,
        'mail' => $mail,
        'pass' => $this->passwordGenerator->generate(),
        'status' => TRUE,
      ]);
      assert($created_user instanceof UserInterface);
      $created_user->save();
      $this->orderAssignment->assign($order, $created_user, FALSE);
      if (!empty($configuration['guest_new_account_notify'])) {
        _user_mail_notify('register_admin_created', $created_user);
      }
    }
    elseif ($existing_user instanceof UserInterface && $guest_order_assign) {
      $this->orderAssignment->assign($order, $existing_user, FALSE);
    }
  }

}
