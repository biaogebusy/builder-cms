<?php

namespace Drupal\commerce_order\Plugin\Field\FieldFormatter;

use Drupal\commerce_product\Entity\ProductVariationInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Field\Attribute\FieldFormatter;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Plugin implementation of the 'commerce_order_item_title' formatter.
 */
#[FieldFormatter(
  id: "commerce_order_item_title",
  label: new TranslatableMarkup("Order item title (with SKU)"),
  description: new TranslatableMarkup("Appends the variation SKU for order items referencing product variations."),
  field_types: ["string"],
)]
class OrderItemTitleFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item */
    $order_item = $items->getEntity();
    $elements[0] = [
      '#theme' => 'commerce_order_item_title',
      '#label' => $order_item->label(),
    ];
    if ($order_item->getPurchasedEntity() instanceof ProductVariationInterface) {
      $elements[0]['#sku'] = $order_item->getPurchasedEntity()->getSku();
    }
    $cacheability = new CacheableMetadata();
    $cacheability->addCacheableDependency($order_item);
    $cacheability->applyTo($elements[0]);

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public static function isApplicable(FieldDefinitionInterface $field_definition): bool {
    return $field_definition->getTargetEntityTypeId() === 'commerce_order_item' && $field_definition->getName() === 'title';
  }

}
