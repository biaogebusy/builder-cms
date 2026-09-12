<?php

namespace Drupal\commerce_order\Plugin\views\field;

use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\views\Attribute\ViewsField;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Field handler to present inline order item operations.
 */
#[ViewsField("commerce_order_item_inline_operations")]
class OrderItemInlineOperations extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $order_item = $this->getEntity($values);
    assert($order_item instanceof OrderItemInterface);

    $links = $this->getOperationLinks($order_item);
    if (empty($links)) {
      return [];
    }

    $build = [
      '#attached' => [
        'library' => ['core/drupal.dialog.ajax'],
      ],
      '#theme' => 'commerce_order_item_inline_operations',
      '#links' => $links,
    ];

    // Attach full cacheability metadata from the order item.
    CacheableMetadata::createFromObject($order_item)->applyTo($build);

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // No query alteration needed.
  }

  /**
   * Returns the list of renderable operation links for an order item.
   *
   * @param \Drupal\commerce_order\Entity\OrderItemInterface $order_item
   *   The order item entity.
   *
   * @return array
   *   Render arrays for links that are accessible to the current user.
   */
  private function getOperationLinks(OrderItemInterface $order_item): array {
    $operations = [];

    $link_definitions = [
      'edit' => [
        'title' => $this->t('edit'),
        'url' => $order_item->toUrl('edit-form'),
      ],
      'delete' => [
        'title' => $this->t('delete'),
        'url' => $order_item->toUrl('delete-form'),
      ],
    ];

    foreach ($link_definitions as $operation => $definition) {
      if (!$definition['url']->access()) {
        continue;
      }

      $operations[$operation] = [
        '#type' => 'link',
        '#title' => $definition['title'],
        '#url' => $definition['url'],
        '#attributes' => [
          'class' => [
            "order-item-{$operation}-action",
            'link',
            'use-ajax',
          ],
          'data-dialog-type' => 'modal',
          'data-dialog-options' => Json::encode([
            'width' => 880,
          ]),
        ],
      ];
    }

    return $operations;
  }

}
