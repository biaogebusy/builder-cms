<?php

namespace Drupal\commerce_product;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the list builder for product types.
 */
class ProductTypeListBuilder extends ConfigEntityListBuilder {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    $instance = parent::createInstance($container, $entity_type);
    $instance->entityTypeManager = $container->get('entity_type.manager');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['name'] = $this->t('Product type');
    $header['type'] = $this->t('ID');
    $header['product_variation_types'] = $this->t('Product variation types');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    $variation_types = $this->entityTypeManager->getStorage('commerce_product_variation_type')->loadMultiple($entity->getVariationTypeIds());
    $row['name'] = $entity->label();
    $row['type'] = $entity->id();
    $row['product_variation_type'] = ['data' => []];
    foreach ($variation_types as $variation_type) {
      $row['product_variation_type']['data'][] = [
        '#type' => 'link',
        '#title' => $variation_type->label(),
        '#url' => $variation_type->toUrl('edit-form'),
        '#suffix' => '<br />',
      ];
    }
    return $row + parent::buildRow($entity);
  }

}
