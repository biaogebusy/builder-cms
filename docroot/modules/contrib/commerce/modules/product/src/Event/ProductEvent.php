<?php

namespace Drupal\commerce_product\Event;

use Drupal\commerce\EventBase;
use Drupal\commerce_product\Entity\ProductInterface;

/**
 * Defines the product event.
 *
 * @see \Drupal\commerce_product\Event\ProductEvents
 */
class ProductEvent extends EventBase {

  /**
   * The product.
   *
   * @var \Drupal\commerce_product\Entity\ProductInterface
   */
  protected $product;

  /**
   * Constructs a new ProductEvent.
   *
   * @param \Drupal\commerce_product\Entity\ProductInterface $product
   *   The product.
   */
  public function __construct(ProductInterface $product) {
    $this->product = $product;
  }

  /**
   * Gets the product.
   *
   * @return \Drupal\commerce_product\Entity\ProductInterface
   *   The product.
   */
  public function getProduct() {
    return $this->product;
  }

}
