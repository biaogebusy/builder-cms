<?php

namespace Drupal\commerce_order;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Entity\ContentEntityStorageInterface;

/**
 * Defines the interface for order storage.
 */
interface OrderStorageInterface extends ContentEntityStorageInterface {

  /**
   * Loads the unchanged entity, bypassing the static cache, and locks it.
   *
   * This implements explicit, pessimistic locking as opposed to the optimistic
   * locking that will log or prevent a conflicting save. Use this method for
   * use cases that load an order with the explicit purpose of immediately
   * changing and saving it again. Especially if these cases may run in parallel
   * to others, for example notification/return callbacks and termination
   * events.
   *
   * @param int $order_id
   *   The order ID.
   *
   * @return \Drupal\commerce_order\Entity\OrderInterface|null
   *   The loaded order or NULL if the entity cannot be loaded.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   *   Thrown if the lock could not be acquired.
   */
  public function loadForUpdate(int $order_id): ?OrderInterface;

}
