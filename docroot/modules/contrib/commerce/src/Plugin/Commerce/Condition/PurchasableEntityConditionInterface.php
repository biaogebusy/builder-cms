<?php

namespace Drupal\commerce\Plugin\Commerce\Condition;

/**
 * Defines the interface for conditions that deal with purchasable entities.
 */
interface PurchasableEntityConditionInterface {

  /**
   * Gets the configured purchasable entity IDS.
   *
   * @return int|string[]
   *   An array of purchasable entity IDs.
   */
  public function getPurchasableEntityIds();

  /**
   * Gets the configured purchasable entities.
   *
   * @return \Drupal\commerce\PurchasableEntityInterface[]
   *   An array of purchasable entities.
   */
  public function getPurchasableEntities();

}
