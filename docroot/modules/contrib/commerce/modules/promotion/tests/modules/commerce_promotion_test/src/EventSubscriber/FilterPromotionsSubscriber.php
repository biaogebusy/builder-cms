<?php

namespace Drupal\commerce_promotion_test\EventSubscriber;

use Drupal\commerce_promotion\Event\FilterPromotionsEvent;
use Drupal\commerce_promotion\Event\PromotionEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class FilterPromotionsSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      PromotionEvents::FILTER_PROMOTIONS => 'onFilter',
    ];
  }

  /**
   * Filters out promotions listed in an order's data attribute.
   *
   * @param \Drupal\commerce_promotion\Event\FilterPromotionsEvent $event
   *   The event.
   */
  public function onFilter(FilterPromotionsEvent $event) {
    $promotions = $event->getPromotions();
    $excluded_promotions = $event->getOrder()->getData('excluded_promotions', []);
    foreach ($promotions as $promotion_id => $promotion) {
      if (in_array($promotion->id(), $excluded_promotions)) {
        unset($promotions[$promotion_id]);
      }
    }
    $event->setPromotions($promotions);
  }

}
