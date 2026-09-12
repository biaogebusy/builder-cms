<?php

namespace Drupal\commerce_cart\Plugin\QueueWorker;

use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\Attribute\QueueWorker;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\commerce\Interval;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Deletes expired carts.
 */
#[QueueWorker(
  id: 'commerce_cart_expiration',
  title: new TranslatableMarkup('Cart expiration'),
  cron: ['time' => 30],
)]
class CartExpiration extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    $orders = [];
    $order_storage = $this->entityTypeManager->getStorage('commerce_order');
    $order_type_storage = $this->entityTypeManager->getStorage('commerce_order_type');
    foreach ($data as $order_id) {
      // Skip the OrderRefresh process to keep the changed timestamp intact.
      /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
      $order = $order_storage->loadUnchanged($order_id);
      if (!$order || $order->isLocked()) {
        continue;
      }
      /** @var \Drupal\commerce_order\Entity\OrderTypeInterface $order_type */
      $order_type = $order_type_storage->load($order->bundle());
      $cart_expiration = $order_type->getThirdPartySetting('commerce_cart', 'cart_expiration');
      // Confirm that cart expiration has not been disabled after queueing.
      if (empty($cart_expiration)) {
        continue;
      }

      $current_date = new DrupalDateTime('now');
      $interval = new Interval($cart_expiration['number'], $cart_expiration['unit']);
      $expiration_date = $interval->subtract($current_date);
      $expiration_timestamp = $expiration_date->getTimestamp();
      // Make sure that the cart order still qualifies for expiration.
      if (($cart_expiration['anonymous_only'] ?? FALSE) && $order->getCustomerId() > 0) {
        continue;
      }
      if ($order->get('cart')->value && $order->getChangedTime() <= $expiration_timestamp) {
        $orders[] = $order;
      }
    }

    $order_storage->delete($orders);
  }

}
