<?php

namespace Drupal\commerce;

use Drupal\Core\Entity\EntityTypeInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;

class PurchasableEntityTypeRepository implements PurchasableEntityTypeRepositoryInterface {

  /**
   * Constructs a new PurchasableEntityTypeRepository.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(protected EntityTypeManagerInterface $entityTypeManager) {}

  /**
   * {@inheritdoc}
   */
  public function getPurchasableEntityTypes() {
    return array_filter($this->entityTypeManager->getDefinitions(), static function (EntityTypeInterface $entity_type) {
      return $entity_type->entityClassImplements(PurchasableEntityInterface::class);
    });
  }

  /**
   * {@inheritdoc}
   */
  public function getPurchasableEntityTypeLabels() {
    return array_map(static function (EntityTypeInterface $entity_type) {
      return $entity_type->getLabel();
    }, $this->getPurchasableEntityTypes());
  }

  /**
   * {@inheritdoc}
   */
  public function getDefaultPurchasableEntityType() {
    $purchasable_entity_types = $this->getPurchasableEntityTypes();
    // Privilege commerce_product_variation as the default type if it exists.
    return $purchasable_entity_types['commerce_product_variation'] ?? reset($purchasable_entity_types);
  }

}
