<?php

namespace Drupal\commerce_product;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Defines the list builder for product types.
 */
class ProductTypeListBuilder extends ConfigEntityListBuilder {

  /**
   * The variation type storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $variationTypeStorage;

  /**
   * Constructs a new ProductTypeListBuilder object.
   *
   * @param \Drupal\Core\Entity\EntityTypeInterface $entity_type
   *   The entity type definition.
   * @param \Drupal\Core\Entity\EntityStorageInterface $storage
   *   The entity storage.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(EntityTypeInterface $entity_type, EntityStorageInterface $storage, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($entity_type, $storage);

    $this->variationTypeStorage = $entity_type_manager->getStorage('commerce_product_variation_type');
  }

  /**
   * {@inheritdoc}
   */
  public static function createInstance(ContainerInterface $container, EntityTypeInterface $entity_type) {
    return new static(
      $entity_type,
      $container->get('entity_type.manager')->getStorage($entity_type->id()),
      $container->get('entity_type.manager')
    );
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
    $variation_types = $this->variationTypeStorage->loadMultiple($entity->getVariationTypeIds());
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
