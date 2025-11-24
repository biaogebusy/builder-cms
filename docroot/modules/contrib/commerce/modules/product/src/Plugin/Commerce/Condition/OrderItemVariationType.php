<?php

namespace Drupal\commerce_product\Plugin\Commerce\Condition;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\commerce\Attribute\CommerceCondition;
use Drupal\commerce\Plugin\Commerce\Condition\ConditionBase;

/**
 * Provides the product variation type condition for order items.
 */
#[CommerceCondition(
  id: "order_item_variation_type",
  label: new TranslatableMarkup("Product variation type"),
  entity_type: "commerce_order_item",
  display_label: new TranslatableMarkup("Product variation types"),
  category: new TranslatableMarkup("Products"),
)]
class OrderItemVariationType extends ConditionBase {

  use VariationTypeTrait;

  /**
   * {@inheritdoc}
   */
  public function evaluate(EntityInterface $entity) {
    $this->assertEntity($entity);
    /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item */
    $order_item = $entity;
    /** @var \Drupal\commerce_product\Entity\ProductVariationInterface $purchased_entity */
    $purchased_entity = $order_item->getPurchasedEntity();
    if (!$purchased_entity || $purchased_entity->getEntityTypeId() != 'commerce_product_variation') {
      return FALSE;
    }

    return in_array($purchased_entity->bundle(), $this->configuration['variation_types']);
  }

}
