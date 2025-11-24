<?php

namespace Drupal\commerce_product\Plugin\Commerce\Condition;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\commerce\Attribute\CommerceCondition;
use Drupal\commerce\Plugin\Commerce\Condition\ConditionBase;

/**
 * Provides the product type condition for orders.
 */
#[CommerceCondition(
  id: "order_product_type",
  label: new TranslatableMarkup("Product type"),
  entity_type: "commerce_order",
  display_label: new TranslatableMarkup("Order contains product types"),
  category: new TranslatableMarkup("Products"),
)]
class OrderProductType extends ConditionBase {

  use ProductTypeTrait;

  /**
   * {@inheritdoc}
   */
  public function evaluate(EntityInterface $entity) {
    $this->assertEntity($entity);
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $entity;
    foreach ($order->getItems() as $order_item) {
      /** @var \Drupal\commerce_product\Entity\ProductVariationInterface $purchased_entity */
      $purchased_entity = $order_item->getPurchasedEntity();
      if (!$purchased_entity || $purchased_entity->getEntityTypeId() != 'commerce_product_variation') {
        continue;
      }
      $product_type = $purchased_entity->getProduct()->bundle();
      if (in_array($product_type, $this->configuration['product_types'])) {
        return TRUE;
      }
    }

    return FALSE;
  }

}
