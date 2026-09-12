<?php

namespace Drupal\commerce_cart;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\commerce\CronInterface;
use Drupal\commerce\Interval;

/**
 * Deletes expired cart orders.
 */
class Cron implements CronInterface {

  /**
   * Constructs a new Cron object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(protected EntityTypeManagerInterface $entityTypeManager) {}

  /**
   * {@inheritdoc}
   */
  public function run(): void {
    /** @var \Drupal\commerce_order\OrderStorageInterface $order_storage */
    $order_storage = $this->entityTypeManager->getStorage('commerce_order');
    $order_type_storage = $this->entityTypeManager->getStorage('commerce_order_type');
    /** @var \Drupal\commerce_order\Entity\OrderTypeInterface[] $order_types */
    $order_types = $order_type_storage->loadMultiple();
    foreach ($order_types as $order_type) {
      $cart_expiration = $order_type->getThirdPartySetting('commerce_cart', 'cart_expiration');
      if (empty($cart_expiration)) {
        continue;
      }

      $interval = new Interval($cart_expiration['number'], $cart_expiration['unit']);
      $anonymous_only = $cart_expiration['anonymous_only'] ?? FALSE;

      $order_ids = $this->getOrderIds($order_type->id(), $interval, $anonymous_only);
      // Note that we don't load multiple orders at once to skip the order
      // refresh process triggered on load.
      foreach ($order_ids as $order_id) {
        /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
        $order = $order_storage->loadUnchanged($order_id);
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
   * @param bool $anonymous_only
   *   (Optional) Whether to include anonymous orders only. Defaults to FALSE.
   *
   * @return array
   *   The order IDs.
   */
  protected function getOrderIds(string $order_type_id, Interval $interval, bool $anonymous_only = FALSE) {
    /** @var \Drupal\commerce_order\OrderStorageInterface $order_storage */
    $order_storage = $this->entityTypeManager->getStorage('commerce_order');
    $current_date = new DrupalDateTime('now');
    $expiration_date = $interval->subtract($current_date);
    $query = $order_storage->getQuery()
      ->condition('type', $order_type_id)
      ->condition('changed', $expiration_date->getTimestamp(), '<=')
      ->condition('locked', FALSE)
      ->notExists('placed')
      ->condition('cart', TRUE)
      ->range(0, 250)
      ->accessCheck(FALSE)
      ->addTag('commerce_cart_expiration');
    if ($anonymous_only) {
      $query->condition('uid', 0);
    }
    $ids = $query->execute();

    return $ids;
  }

}
