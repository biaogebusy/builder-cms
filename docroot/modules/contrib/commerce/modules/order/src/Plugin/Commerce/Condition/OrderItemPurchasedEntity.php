<?php

namespace Drupal\commerce_order\Plugin\Commerce\Condition;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\commerce\Attribute\CommerceCondition;
use Drupal\commerce_order\Entity\OrderItemInterface;

/**
 * Provides the variation condition for order items.
 */
#[CommerceCondition(
  id: "order_item_purchased_entity",
  label: new TranslatableMarkup('Purchased entity'),
  entity_type: "commerce_order_item",
  display_label: new TranslatableMarkup("Specific purchased item"),
  category: new TranslatableMarkup("Purchased items"),
  weight: -1,
  deriver: PurchasedEntityConditionDeriver::class,
)]
class OrderItemPurchasedEntity extends PurchasedEntityConditionBase {

  /**
   * {@inheritdoc}
   */
  public function evaluate(EntityInterface $entity) {
    $this->assertEntity($entity);
    assert($entity instanceof OrderItemInterface);
    return $this->isValid($entity->getPurchasedEntity());
  }

}
