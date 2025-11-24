<?php

/**
 * @file
 * Provides views execution hooks for Cart.
 */

use Drupal\views\Plugin\views\query\QueryPluginBase;
use Drupal\views\ViewExecutable;

/**
 * Implements hook_views_query_alter().
 */
function commerce_cart_views_query_alter(ViewExecutable $view, QueryPluginBase $query): void {
  if ($view->id() === 'commerce_orders') {
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
