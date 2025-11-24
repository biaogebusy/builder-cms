<?php

namespace Drupal\commerce_checkout\Event;

use Drupal\Core\Session\AccountInterface;
use Drupal\commerce\EventBase;
use Drupal\commerce_order\Entity\OrderInterface;

/**
 * Defines the register during checkout event.
 *
 * @see \Drupal\commerce_checkout\Event\CheckoutEvents
 */
class CheckoutRegisterEvent extends EventBase {

  /**
   * Constructs a new CheckoutRegisterEvent object.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The created account.
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The checkout order.
   */
  public function __construct(protected AccountInterface $account, protected OrderInterface $order) {}

  /**
   * Gets the created account.
   *
   * @return \Drupal\Core\Session\AccountInterface
   *   The created account.
   */
  public function getAccount() {
    return $this->account;
  }

  /**
   * Gets the checkout order.
   *
   * @return \Drupal\commerce_order\Entity\OrderInterface
   *   The checkout order.
   */
  public function getOrder() {
    return $this->order;
  }

}
