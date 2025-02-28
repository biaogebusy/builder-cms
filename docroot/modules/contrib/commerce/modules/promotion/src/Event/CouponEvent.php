<?php

namespace Drupal\commerce_promotion\Event;

use Drupal\commerce\EventBase;
use Drupal\commerce_promotion\Entity\CouponInterface;

/**
 * Defines the coupon event.
 *
 * @see \Drupal\commerce_promotion\Event\PromotionEvents
 */
class CouponEvent extends EventBase {

  /**
   * The coupon.
   *
   * @var \Drupal\commerce_promotion\Entity\CouponInterface
   */
  protected $coupon;

  /**
   * Constructs a new CouponEvent.
   *
   * @param \Drupal\commerce_promotion\Entity\CouponInterface $coupon
   *   The coupon.
   */
  public function __construct(CouponInterface $coupon) {
    $this->coupon = $coupon;
  }

  /**
   * Gets the coupon.
   *
   * @return \Drupal\commerce_promotion\Entity\CouponInterface
   *   The coupon.
   */
  public function getCoupon() {
    return $this->coupon;
  }

}
