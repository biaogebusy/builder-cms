<?php

namespace Drupal\commerce_product;

use Drupal\commerce\CommerceContentEntityStorage;
use Drupal\commerce_product\Entity\ProductInterface;
use Drupal\commerce_product\Event\FilterVariationsEvent;
use Drupal\commerce_product\Event\ProductEvents;
use Drupal\Core\Entity\EntityTypeInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the product variation storage.
 */
class ProductVariationStorage extends CommerceContentEntityStorage implements ProductVariationStorageInterface {

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * Static cache of enabled variations.
   *
   * @var array
   */
  protected $enabledVariations = [];

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    $instance = parent::createInstance($container, $entity_type);
    $instance->requestStack = $container->get('request_stack');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function loadBySku($sku) {
    $variations = $this->loadByProperties(['sku' => $sku]);
    $variation = reset($variations);

    return $variation ?: NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function loadFromContext(ProductInterface $product) {
    $current_request = $this->requestStack->getCurrentRequest();
    if ($variation_id = $current_request->query->get('v')) {
      if (in_array($variation_id, $product->getVariationIds())) {
        /** @var \Drupal\commerce_product\Entity\ProductVariationInterface $variation */
        $variation = $this->load($variation_id);
        if ($variation->isPublished() && $variation->access('view')) {
          return $variation;
        }
      }
    }
    return $product->getDefaultVariation();
  }

  /**
   * {@inheritdoc}
   */
  public function loadEnabled(ProductInterface $product) {
    // Check if the enabled variations were already determined for this product.
    if (array_key_exists($product->id(), $this->enabledVariations)) {
      return $this->enabledVariations[$product->id()];
    }
    $ids = [];
    foreach ($product->variations as $variation) {
      $ids[$variation->target_id] = $variation->target_id;
    }
    if (empty($ids)) {
      $this->enabledVariations[$product->id()] = [];
      return [];
    }
    // Speed up loading by filtering out the IDs of disabled variations.
    $query = $this->getQuery()
      ->accessCheck(TRUE)
      ->addTag('entity_access')
      ->condition('status', TRUE)
      ->condition('variation_id', $ids, 'IN');
    $result = $query->execute();
    if (empty($result)) {
      $this->enabledVariations[$product->id()] = [];
      return [];
    }
    // Restore the original sort order.
    $result = array_intersect_key($ids, $result);

    /** @var \Drupal\commerce_product\Entity\ProductVariationInterface $enabled_variation */
    $enabled_variations = $this->loadMultiple($result);
    // Allow modules to apply own filtering (based on date, stock, etc).
    $event = new FilterVariationsEvent($product, $enabled_variations);
    $this->eventDispatcher->dispatch($event, ProductEvents::FILTER_VARIATIONS);
    $enabled_variations = $event->getVariations();
    // Filter out variations that can't be accessed.
    foreach ($enabled_variations as $variation_id => $enabled_variation) {
      if (!$enabled_variation->access('view')) {
        unset($enabled_variations[$variation_id]);
      }
    }
    // Populate the static cache so that the next time this method is called
    // we don't perform the same expansive logic during the same request.
    $this->enabledVariations[$product->id()] = $enabled_variations;

    return $enabled_variations;
  }

}
