<?php

namespace Drupal\commerce_promotion\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\commerce_promotion\PromotionUsageInterface;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderEventSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a new OrderEventSubscriber object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\commerce_promotion\PromotionUsageInterface $usage
   *   The promotion usage.
   */
  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected PromotionUsageInterface $usage,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    $events = [
      'commerce_order.place.pre_transition' => 'registerUsage',
    ];
    return $events;
  }

  /**
   * Registers promotion usage when the order is placed.
   *
   * @param \Drupal\state_machine\Event\WorkflowTransitionEvent $event
   *   The workflow transition event.
   */
  public function registerUsage(WorkflowTransitionEvent $event): void {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $event->getEntity();
    $coupon_promotion_ids = [];
    foreach ($order->coupons->referencedEntities() as $coupon) {
      /** @var \Drupal\commerce_promotion\Entity\CouponInterface $coupon */
      $this->usage->register($order, $coupon->getPromotion(), $coupon);
      $coupon_promotion_ids[] = $coupon->getPromotionId();
    }

    $promotion_ids = [];
    $adjustments = $order->collectAdjustments();
    foreach ($adjustments as $adjustment) {
      if ($adjustment->getType() !== 'promotion' &&
        $adjustment->getType() !== 'shipping_promotion') {
        continue;
      }

      $promotion_id = $adjustment->getSourceId();
      if ($promotion_id && !in_array($promotion_id, $coupon_promotion_ids)) {
        $promotion_ids[$promotion_id] = $promotion_id;
      }
    }

    if ($promotion_ids) {
      $promotions = $this->entityTypeManager->getStorage('commerce_promotion')->loadMultiple($promotion_ids);

      /** @var \Drupal\commerce_promotion\Entity\PromotionInterface $promotion */
      foreach ($promotions as $promotion) {
        $this->usage->register($order, $promotion);
      }
    }
  }

}
