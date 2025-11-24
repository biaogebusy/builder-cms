<?php

namespace Drupal\commerce_order\Plugin\Commerce\Condition;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\commerce\Attribute\CommerceCondition;
use Drupal\commerce_order\Entity\OrderInterface;

/**
 * Provides the variation condition for orders.
 */
#[CommerceCondition(
  id: "order_purchased_entity",
  label: new TranslatableMarkup("Purchased entity"),
  entity_type: "commerce_order",
  display_label: new TranslatableMarkup("Order contains specific purchased item"),
  category: new TranslatableMarkup("Purchased items"),
  weight: -1,
  deriver: PurchasedEntityConditionDeriver::class,
)]
class OrderPurchasedEntity extends PurchasedEntityConditionBase {

  /**
   * {@inheritdoc}
   */
  public function evaluate(EntityInterface $entity) {
    $this->assertEntity($entity);
    assert($entity instanceof OrderInterface);
    foreach ($entity->getItems() as $order_item) {
      if ($this->isValid($order_item->getPurchasedEntity())) {
        return TRUE;
      }
    }

    return FALSE;
  }

}
