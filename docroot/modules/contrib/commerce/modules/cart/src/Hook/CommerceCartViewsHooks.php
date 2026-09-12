<?php

namespace Drupal\commerce_cart\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\views\Plugin\views\query\QueryPluginBase;
use Drupal\views\ViewExecutable;

/**
 * Views hook implementations for Commerce Cart.
 */
class CommerceCartViewsHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_views_data_alter().
   */
  #[Hook('views_data_alter')]
  public function viewsDataAlter(array &$data): void {
    $data['commerce_order_item']['edit_quantity']['field'] = [
      'title' => $this->t('Quantity text field'),
      'help' => $this->t('Adds a text field for editing the quantity.'),
      'id' => 'commerce_order_item_edit_quantity',
    ];
    $data['commerce_order_item']['remove_button']['field'] = [
      'title' => $this->t('Remove button'),
      'help' => $this->t('Adds a button for removing the order item.'),
      'id' => 'commerce_order_item_remove_button',
    ];
    $data['commerce_order']['empty_cart_button'] = [
      'title' => $this->t('Empty cart button'),
      'help' => $this->t('Adds a button for emptying the cart.'),
      'area' => [
        'id' => 'commerce_order_empty_cart_button',
      ],
    ];
  }

  /**
   * Implements hook_views_query_alter().
   */
  #[Hook('views_query_alter')]
  public function viewsQueryAlter(ViewExecutable $view, QueryPluginBase $query): void {
    if ($view->id() !== 'commerce_orders') {
      return;
    }
    // Filter out carts, they have their own tab.
    $base_tables = array_keys($view->getBaseTables());
    if ($base_table = reset($base_tables)) {
      $query->addWhere(0, "$base_table.cart", 1, '<>');
    }
    else {
      $query->addWhere(0, 'cart', 1, '<>');
    }
  }

}
