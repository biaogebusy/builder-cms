<?php

namespace Drupal\commerce_product\ContextProvider;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\Context\Context;
use Drupal\Core\Plugin\Context\ContextProviderInterface;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\commerce_product\Entity\ProductTypeInterface;

/**
 * Sets the current product as context on commerce_product routes.
 *
 * @todo Remove once core gets a generic EntityRouteContext.
 */
class ProductRouteContext implements ContextProviderInterface {

  use StringTranslationTrait;

  /**
   * Constructs a new ProductRouteContext object.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The route match.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(protected RouteMatchInterface $routeMatch, protected EntityTypeManagerInterface $entityTypeManager) {}

  /**
   * {@inheritdoc}
   */
  public function getRuntimeContexts(array $unqualified_context_ids) {
    $context_definition = new EntityContextDefinition('entity:commerce_product', NULL, FALSE);
    $value = NULL;
    if ($product = $this->routeMatch->getParameter('commerce_product')) {
      $value = $product;
    }
    elseif ($product_type = $this->routeMatch->getParameter('commerce_product_type')) {
      $product_storage = $this->entityTypeManager->getStorage('commerce_product');
      $product_type_id = $product_type instanceof ProductTypeInterface ? $product_type->id() : $product_type;
      $value = $product_storage->createWithSampleValues($product_type_id);
    }

    $cacheability = new CacheableMetadata();
    $cacheability->setCacheContexts(['route']);
    $context = new Context($context_definition, $value);
    $context->addCacheableDependency($cacheability);

    return ['commerce_product' => $context];
  }

  /**
   * {@inheritdoc}
   */
  public function getAvailableContexts() {
    $context = new Context(new EntityContextDefinition(
      'entity:commerce_product', $this->t('Product from URL')
    ));
    return ['commerce_product' => $context];
  }

}
