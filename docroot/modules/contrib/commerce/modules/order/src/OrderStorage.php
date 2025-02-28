<?php

namespace Drupal\commerce_order;

use Drupal\commerce\CommerceContentEntityStorage;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Event\OrderEvent;
use Drupal\commerce_order\Event\OrderEvents;
use Drupal\commerce_order\Exception\OrderLockedSaveException;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\Core\Entity\EntityTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the order storage.
 */
class OrderStorage extends CommerceContentEntityStorage implements OrderStorageInterface {

  /**
   * The order refresh.
   *
   * @var \Drupal\commerce_order\OrderRefreshInterface
   */
  protected $orderRefresh;

  /**
   * Whether the order refresh should be skipped.
   *
   * @var bool
   */
  protected $skipRefresh = FALSE;

  /**
   * List of successfully locked orders.
   *
   * @var int[]
   */
  protected $updateLocks = [];

  /***
   * The lock backend.
   *
   * @var \Drupal\Core\Lock\LockBackendInterface
   */
  protected $lockBackend;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    $instance = parent::createInstance($container, $entity_type);
    $instance->orderRefresh = $container->get('commerce_order.order_refresh');
    $instance->lockBackend = $container->get('lock');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function loadUnchanged($id) {
    // This method is used by the entity save process, triggering an order
    // refresh would cause a save-within-a-save.
    $this->skipRefresh = TRUE;
    $unchanged_order = parent::loadUnchanged($id);
    $this->skipRefresh = FALSE;
    return $unchanged_order;
  }

  /**
   * {@inheritdoc}
   */
  protected function invokeHook($hook, EntityInterface $entity) {
    if ($hook == 'presave') {
      // Order::preSave() has completed, now run the storage-level pre-save
      // tasks. These tasks can modify the order, so they need to run
      // before the entity/field hooks are invoked.
      $this->doOrderPreSave($entity);
    }

    parent::invokeHook($hook, $entity);
  }

  /**
   * Performs order-specific pre-save tasks.
   *
   * This includes:
   * - Refreshing the order.
   * - Recalculating the total price.
   * - Dispatching the "order paid" event.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   */
  protected function doOrderPreSave(OrderInterface $order) {
    if (!$order->isNew() && !isset($this->updateLocks[$order->id()]) && !$this->lockBackend->lockMayBeAvailable($this->getLockId($order->id()))) {
      // This is updating an order that someone else has locked.
      $mismatch_exception = new OrderLockedSaveException('Attempted to save order ' . $order->id() . ' that is locked for updating. Use OrderStorage::loadForUpdate().');
      $log_only = $this->getEntityType()->get('log_version_mismatch');
      if ($log_only) {
        watchdog_exception('commerce_order', $mismatch_exception);
      }
      else {
        throw $mismatch_exception;
      }
    }

    // Ensure the order doesn't reference any removed order item by resetting
    // the "order_items" field with order items that were successfully loaded
    // from the database.
    $order->set('order_items', $order->getItems());
    if ($order->getRefreshState() == OrderInterface::REFRESH_ON_SAVE) {
      $this->orderRefresh->refresh($order);
    }
    // Only the REFRESH_ON_LOAD state needs to be persisted on the entity.
    if ($order->getRefreshState() != OrderInterface::REFRESH_ON_LOAD) {
      $order->setRefreshState(NULL);
    }
    $order->recalculateTotalPrice();

    // Notify other modules if the order has been fully paid.
    $original_paid = isset($order->original) ? $order->original->isPaid() : FALSE;
    if ($order->isPaid() && !$original_paid) {
      // Order::preSave() initializes the 'paid_event_dispatched' flag to FALSE.
      // Skip dispatch if it already happened once (flag is TRUE), or if the
      // order was completed before Commerce 8.x-2.10 (flag is NULL).
      if ($order->getData('paid_event_dispatched') === FALSE) {
        $event = new OrderEvent($order);
        $this->eventDispatcher->dispatch($event, OrderEvents::ORDER_PAID);
        $order->setData('paid_event_dispatched', TRUE);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function postLoad(array &$entities) {
    if (!$this->skipRefresh) {
      /** @var \Drupal\commerce_order\Entity\OrderInterface[] $entities */
      foreach ($entities as $entity) {
        $explicitly_requested = $entity->getRefreshState() == OrderInterface::REFRESH_ON_LOAD;
        if ($explicitly_requested || $this->orderRefresh->shouldRefresh($entity)) {
          // Reuse the doPostLoad logic.
          $entity->setRefreshState(OrderInterface::REFRESH_ON_SAVE);
          $entity->save();
        }
      }
    }

    return parent::postLoad($entities);
  }

  /**
   * {@inheritdoc}
   */
  public function save(EntityInterface $entity) {
    try {
      return parent::save($entity);
    }
    finally {
      // Release the update lock if it was acquired for this entity.
      if (isset($this->updateLocks[$entity->id()])) {
        $this->lockBackend->release($this->getLockId($entity->id()));
        unset($this->updateLocks[$entity->id()]);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function loadForUpdate(int $order_id): ?OrderInterface {
    $lock_id = $this->getLockId($order_id);
    if ($this->lockBackend->acquire($lock_id)) {
      $this->updateLocks[$order_id] = TRUE;
      return $this->loadUnchanged($order_id);
    }
    else {
      // Failed to acquire initial lock, wait for it to free up.
      if (!$this->lockBackend->wait($lock_id) && $this->lockBackend->acquire($lock_id)) {
        $this->updateLocks[$order_id] = TRUE;
        return $this->loadUnchanged($order_id);
      }
      throw new EntityStorageException('Failed to acquire lock');
    }
  }

  /**
   * Gets the lock ID for the given order ID.
   *
   * @param int $order_id
   *   The order ID.
   *
   * @return string
   *   The lock ID.
   */
  protected function getLockId(int $order_id): string {
    return 'commerce_order_update:' . $order_id;
  }

}
