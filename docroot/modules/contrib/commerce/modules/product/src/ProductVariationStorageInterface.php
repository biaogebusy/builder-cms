<?php

namespace Drupal\commerce_product;

use Drupal\Core\Entity\ContentEntityStorageInterface;
use Drupal\commerce_product\Entity\ProductInterface;

/**
 * Defines the interface for product variation storage.
 */
interface ProductVariationStorageInterface extends ContentEntityStorageInterface {

  /**
   * Loads the product variation for the given SKU.
   *
   * @param string $sku
   *   The SKU.
   *
   * @return \Drupal\commerce_product\Entity\ProductVariationInterface|null
   *   The product variation, or NULL if not found.
   */
  public function loadBySku($sku);

  /**
   * Loads the product variation from context.
   *
   * Uses the variation specified in the URL (?v=) if it is active and
   * belongs to the current product. If no valid variation is found in the
   * URL, falls back to the product's default variation. Returns NULL if
   * no variation can be resolved.
   *
   * Note: The returned variation is not guaranteed to be enabled; the caller
   * must validate it against the list from loadEnabled().
   *
   * @param \Drupal\commerce_product\Entity\ProductInterface $product
   *   The current product.
   *
   * @return \Drupal\commerce_product\Entity\ProductVariationInterface|null
   *   The product variation, or NULL if none found.
   */
  public function loadFromContext(ProductInterface $product);

  /**
   * Loads the enabled product variations for the given product.
   *
   * Enabled variations are active variations that have been filtered through
   * the FILTER_VARIATIONS event.
   *
   * @param \Drupal\commerce_product\Entity\ProductInterface $product
   *   The product.
   *
   * @return \Drupal\commerce_product\Entity\ProductVariationInterface[]
   *   The enabled product variations.
   */
  public function loadEnabled(ProductInterface $product);

}
