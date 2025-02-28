<?php

namespace Drupal\commerce_promotion\Entity;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Provides an interface for defining coupon entities.
 */
interface CouponInterface extends ContentEntityInterface, EntityChangedInterface {

  /**
   * Gets the parent promotion.
   *
   * @return \Drupal\commerce_promotion\Entity\PromotionInterface|null
   *   The promotion entity, or null.
   */
  public function getPromotion();

  /**
   * Gets the parent promotion ID.
   *
   * @return int|null
   *   The promotion ID, or null.
   */
  public function getPromotionId();

  /**
   * Gets the coupon code.
   *
   * @return string
   *   Code for the coupon.
   */
  public function getCode();

  /**
   * Sets the coupon code.
   *
   * @param string $code
   *   The coupon code.
   *
   * @return $this
   */
  public function setCode($code);

  /**
   * Gets the coupon creation timestamp.
   *
   * @return int
   *   Creation timestamp of the coupon.
   */
  public function getCreatedTime();

  /**
   * Sets the coupon creation timestamp.
   *
   * @param int $timestamp
   *   The coupon creation timestamp.
   *
   * @return $this
   */
  public function setCreatedTime($timestamp);

  /**
   * Gets the coupon usage limit.
   *
   * Represents the maximum number of times the coupon can be used.
   * 0 for unlimited.
   *
   * @return int
   *   The coupon usage limit.
   */
  public function getUsageLimit();

  /**
   * Sets the coupon usage limit.
   *
   * @param int $usage_limit
   *   The coupon usage limit.
   *
   * @return $this
   */
  public function setUsageLimit($usage_limit);

  /**
   * Gets the per customer coupon usage limit.
   *
   * Represents the maximum number of times the coupon can be used by a customer.
   * 0 for unlimited.
   *
   * @return int
   *   The per customer coupon usage limit.
   */
  public function getCustomerUsageLimit();

  /**
   * Sets the per customer coupon usage limit.
   *
   * @param int $usage_limit_customer
   *   The per customer coupon usage limit.
   *
   * @return $this
   */
  public function setCustomerUsageLimit($usage_limit_customer);

  /**
   * Gets whether the coupon is enabled.
   *
   * @return bool
   *   TRUE if the coupon is enabled, FALSE otherwise.
   */
  public function isEnabled();

  /**
   * Sets whether the coupon is enabled.
   *
   * @param bool $enabled
   *   Whether the coupon is enabled.
   *
   * @return $this
   */
  public function setEnabled($enabled);

  /**
   * Gets the coupon start date/time.
   *
   * The start date/time should always be used in the store timezone.
   * Since the promotion can belong to multiple stores, the timezone
   * isn't known at load/save time, and is provided by the caller instead.
   *
   * Note that the returned date/time value is the same in any timezone,
   * the "2019-10-17 10:00" stored value is returned as "2019-10-17 10:00 CET"
   * for "Europe/Berlin" and "2019-10-17 10:00 ET" for "America/New_York".
   *
   * @param string $store_timezone
   *   The store timezone. E.g. "Europe/Berlin".
   *
   * @return \Drupal\Core\Datetime\DrupalDateTime
   *   The coupon start date/time.
   */
  public function getStartDate($store_timezone = 'UTC');

  /**
   * Sets the coupon start date/time.
   *
   * @param \Drupal\Core\Datetime\DrupalDateTime $start_date
   *   The coupon start date/time.
   *
   * @return $this
   */
  public function setStartDate(DrupalDateTime $start_date);

  /**
   * Gets the coupon end date/time.
   *
   * The end date/time should always be used in the store timezone.
   * Since the promotion can belong to multiple stores, the timezone
   * isn't known at load/save time, and is provided by the caller instead.
   *
   * Note that the returned date/time value is the same in any timezone,
   * the "2019-10-17 11:00" stored value is returned as "2019-10-17 11:00 CET"
   * for "Europe/Berlin" and "2019-10-17 11:00 ET" for "America/New_York".
   *
   * @param string $store_timezone
   *   The store timezone. E.g. "Europe/Berlin".
   *
   * @return \Drupal\Core\Datetime\DrupalDateTime
   *   The coupon end date/time.
   */
  public function getEndDate($store_timezone = 'UTC');

  /**
   * Sets the coupon end date/time.
   *
   * @param \Drupal\Core\Datetime\DrupalDateTime $end_date
   *   The coupon end date/time.
   *
   * @return $this
   */
  public function setEndDate(DrupalDateTime $end_date = NULL);

  /**
   * Checks whether the coupon is available for the given order.
   *
   * Ensures that the parent promotion is available, the coupon
   * is enabled, and the usage limits are respected.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   *
   * @return bool
   *   TRUE if coupon is available, FALSE otherwise.
   */
  public function available(OrderInterface $order);

}
