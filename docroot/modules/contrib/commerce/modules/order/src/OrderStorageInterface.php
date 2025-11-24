<?php

namespace Drupal\commerce_order;

use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\commerce_order\Entity\OrderInterface;

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

  /**
   * Release the order lock.
   *
   * In the normal scenario, the lock will be released automatically when the
   * order is saved. There may be times, however, when we want to release the
   * lock without a save. One is if we waited for the lock and then determined
   * the order is not in a state we can't process. e.g. We wanted to process
   * a draft order, but another process has completed or cancelled the order.
   * Another scenario is if an exception occurs, in which case, we want to
   * release the lock.
   *
   * @param int $order_id
   *   The order ID.
   */
  public function releaseLock(int $order_id): void;

}
