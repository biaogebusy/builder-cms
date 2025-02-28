<?php

namespace Drupal\commerce_promotion;

use Drupal\commerce\CommerceContentEntityStorage;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_promotion\Event\FilterPromotionsEvent;
use Drupal\commerce_promotion\Event\PromotionEvents;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the promotion storage.
 */
class PromotionStorage extends CommerceContentEntityStorage implements PromotionStorageInterface {

  /**
   * The usage.
   *
   * @var \Drupal\commerce_promotion\PromotionUsageInterface
   */
  protected $usage;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    $instance = parent::createInstance($container, $entity_type);
    $instance->usage = $container->get('commerce_promotion.usage');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function loadAvailable(OrderInterface $order, array $offer_ids = []) {
    $date = $order->getCalculationDate()->format(DateTimeItemInterface::DATETIME_STORAGE_FORMAT);

    $query = $this->getQuery();
    $or_condition = $query->orConditionGroup()
      ->condition('end_date', $date, '>')
      ->notExists('end_date');
    $store_condition = $query->orConditionGroup()
      ->notExists('stores')
      ->condition('stores', [$order->getStoreId()], 'IN');
    $query
      ->condition('order_types', [$order->bundle()], 'IN')
      ->condition('start_date', $date, '<=')
      ->condition('status', TRUE)
      ->condition($or_condition)
      ->condition($store_condition)
      ->accessCheck(FALSE);
    if ($offer_ids) {
      $query->condition('offer.target_plugin_id', $offer_ids, 'IN');
    }
    // Only load promotions without coupons. Promotions with coupons are loaded
    // coupon-first in a different process.
    $coupon_condition = $query->orConditionGroup()
      ->notExists('require_coupon')
      ->condition('require_coupon', 0, '=');
    $query
      ->condition($coupon_condition)
      ->notExists('coupons');
    $result = $query->execute();
    if (empty($result)) {
      return [];
    }

    $promotions = $this->loadMultiple($result);
    // Remove any promotions that do not have a usage limit.
    $promotions_with_usage_limits = array_filter($promotions, function ($promotion) {
      /** @var \Drupal\commerce_promotion\Entity\PromotionInterface $promotion */
      return !empty($promotion->getUsageLimit());
    });
    $usages = $this->usage->loadMultiple($promotions_with_usage_limits);
    foreach ($promotions_with_usage_limits as $promotion_id => $promotion) {
      /** @var \Drupal\commerce_promotion\Entity\PromotionInterface $promotion */
      if ($promotion->getUsageLimit() <= $usages[$promotion_id]) {
        unset($promotions[$promotion_id]);
      }
    }
    // Remove any promotions that do not have a customer usage limit.
    $promotions_with_customer_usage_limits = array_filter($promotions, function ($promotion) {
      /** @var \Drupal\commerce_promotion\Entity\PromotionInterface $promotion */
      return !empty($promotion->getCustomerUsageLimit());
    });
    // Email is required for promotions that have customer usage limits.
    $email = $order->getEmail();
    if (!$email) {
      foreach ($promotions_with_customer_usage_limits as $promotion_id => $promotion) {
        unset($promotions[$promotion_id]);
      }
    }
    else {
      $customer_usages = $this->usage->loadMultiple($promotions_with_customer_usage_limits, $email);
      foreach ($promotions_with_customer_usage_limits as $promotion_id => $promotion) {
        /** @var \Drupal\commerce_promotion\Entity\PromotionInterface $promotion */
        if ($promotion->getCustomerUsageLimit() <= $customer_usages[$promotion_id]) {
          unset($promotions[$promotion_id]);
        }
      }
    }
    // Sort the remaining promotions.
    uasort($promotions, [$this->entityType->getClass(), 'sort']);
    $event = new FilterPromotionsEvent($promotions, $order);
    $this->eventDispatcher->dispatch($event, PromotionEvents::FILTER_PROMOTIONS);

    return $event->getPromotions();
  }

}
