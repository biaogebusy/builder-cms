<?php

/**
 * @file
 * Provide views data for the Order module.
 */

/**
 * Implements hook_views_data().
 */
function commerce_order_views_data() {
  $data['views']['commerce_order_total'] = [
    'title' => t('Order total'),
    'help' => t('Displays the order total field, requires an Order ID argument.'),
    'area' => [
      'id' => 'commerce_order_total',
    ],
  ];
  $data['commerce_order']['billing_profile'] = [
    'title' => t('Billing Profile'),
    'help' => t('Reference to the billing profile of a commerce order.'),
    'relationship' => [
      'group' => t('Order'),
      'base' => 'profile',
      'base field' => 'profile_id',
      'field' => 'billing_profile__target_id',
      'id' => 'standard',
      'label' => t('Billing Profile'),
    ],
  ];
  return $data;
}
