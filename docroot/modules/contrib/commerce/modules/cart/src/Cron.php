<?php

namespace Drupal\commerce_cart;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\commerce\CronInterface;
use Drupal\commerce\Interval;

/**
 * Default cron implementation.
 *
 * Queues abandoned carts for expiration (deletion).
 *
 * @see \Drupal\commerce_cart\Plugin\QueueWorker\CartExpiration
 */
class Cron implements CronInterface {

  /**
   * The order storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $orderStorage;

  /**
   * The order type storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $orderTypeStorage;

  /**
   * The commerce_cart_expiration queue.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $queue;

  /**
   * Constructs a new Cron object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->orderStorage = $entity_type_manager->getStorage('commerce_order');
    $this->orderTypeStorage = $entity_type_manager->getStorage('commerce_order_type');
  }

  /**
   * {@inheritdoc}
   */
  public function run(): void {
    /** @var \Drupal\commerce_order\Entity\OrderTypeInterface[] $order_types */
    $order_types = $this->orderTypeStorage->loadMultiple();
    foreach ($order_types as $order_type) {
      $cart_expiration = $order_type->getThirdPartySetting('commerce_cart', 'cart_expiration');
      if (empty($cart_expiration)) {
        continue;
      }

      $interval = new Interval($cart_expiration['number'], $cart_expiration['unit']);
      $order_ids = $this->getOrderIds($order_type->id(), $interval);
      // Note that we don't load multiple orders at once to skip the order
      // refresh process triggered on load.
      foreach ($order_ids as $order_id) {
        /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
        $order = $this->orderStorage->loadUnchanged($order_id);
        if ($order) {
          $order->delete();
        }
      }
    }
  }

  /**
   * Gets the applicable order IDs.
   *
   * @param string $order_type_id
   *   The order type ID.
   * @param \Drupal\commerce\Interval $interval
   *   The expiration interval.
   *
   * @return array
   *   The order IDs.
   */
  protected function getOrderIds($order_type_id, Interval $interval) {
    $current_date = new DrupalDateTime('now');
    $expiration_date = $interval->subtract($current_date);
    $ids = $this->orderStorage->getQuery()
      ->condition('type', $order_type_id)
      ->condition('changed', $expiration_date->getTimestamp(), '<=')
      ->condition('locked', FALSE)
      ->notExists('placed')
      ->condition('cart', TRUE)
      ->range(0, 250)
      ->accessCheck(FALSE)
      ->addTag('commerce_cart_expiration')
      ->execute();

    return $ids;
  }

}
