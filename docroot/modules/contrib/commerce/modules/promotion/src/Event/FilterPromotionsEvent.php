<?php

namespace Drupal\commerce_promotion\Event;

use Drupal\commerce\EventBase;
use Drupal\commerce_order\Entity\OrderInterface;

/**
 * Defines the event for filtering/sorting the available promotions.
 *
 * @see \Drupal\commerce_promotion\Event\PromotionEvents
 */
class FilterPromotionsEvent extends EventBase {

  /**
   * Constructs a new FilterPromotionsEvent object.
   *
   * @param \Drupal\commerce_promotion\Entity\PromotionInterface[] $promotions
   *   The promotions.
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order.
   */
  public function __construct(protected array $promotions, protected OrderInterface $order) {}

  /**
   * Gets the promotions.
   *
   * @return \Drupal\commerce_promotion\Entity\PromotionInterface[]
   *   The promotions.
   */
  public function getPromotions(): array {
    return $this->promotions;
  }

  /**
   * Sets the promotions.
   *
   * @param \Drupal\commerce_promotion\Entity\PromotionInterface[] $promotions
   *   The promotions.
   *
   * @return $this
   */
  public function setPromotions(array $promotions): static {
    $this->promotions = $promotions;
    return $this;
  }

  /**
   * Gets the order.
   *
   * @return \Drupal\commerce_order\Entity\OrderInterface
   *   The order.
   */
  public function getOrder(): OrderInterface {
    return $this->order;
  }

}
