<?php

namespace Drupal\commerce_promotion\Plugin\Commerce\PromotionOffer;

/**
 * Defines the interface for the combination offer.
 */
interface CombinationOfferInterface extends OrderPromotionOfferInterface {

  /**
   * Gets the offers configured.
   *
   * @return \Drupal\commerce_promotion\Plugin\Commerce\PromotionOffer\PromotionOfferInterface[]
   *   The offers configured.
   */
  public function getOffers();

}
