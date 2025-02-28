<?php

/**
 * @file
 * Provide views data for the Promotion module.
 */

/**
 * Implements hook_views_data().
 */
function commerce_promotion_views_data() {
  $data = [];

  // Expose the promotion usage data to views.
  $data['commerce_promotion_usage']['table']['group'] = t('Promotion Usage');
  $data['commerce_promotion_usage']['table']['base'] = [
    'title' => t('Promotion Usage'),
    'field' => 'usage_id',
    'help' => t('Data for Commerce Promotion usage.'),
    'weight' => -10,
  ];
  $data['commerce_promotion_usage']['table']['join'] = [
    'commerce_promotion_coupon' => [
      'left_field' => 'id',
      'field' => 'coupon_id',
    ],
    'commerce_promotion_field_data' => [
      'left_field' => 'promotion_id',
      'field' => 'promotion_id',
    ],
    'commerce_order' => [
      'left_field' => 'order_id',
      'field' => 'order_id',
    ],
    'users_field_data' => [
      'left_field' => 'mail',
      'field' => 'mail',
    ],
  ];
  $data['commerce_promotion_usage']['promotion_id'] = [
    'title' => t('Promotion'),
    'help' => t('The promotion.'),
    'relationship' => [
      'base' => 'commerce_promotion_field_data',
      'base field' => 'promotion_id',
      'id' => 'standard',
      'label' => t('Promotion'),
    ],
    'field' => [
      'id' => 'numeric',
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'numeric',
    ],
    'argument' => [
      'id' => 'standard',
    ],
  ];
  $data['commerce_promotion_usage']['coupon_id'] = [
    'title' => t('Coupon'),
    'help' => t('The coupon.'),
    'relationship' => [
      'base' => 'commerce_promotion_coupon',
      'base field' => 'id',
      'id' => 'standard',
      'label' => t('Coupon'),
    ],
    'field' => [
      'id' => 'numeric',
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'numeric',
    ],
    'argument' => [
      'id' => 'standard',
    ],
  ];
  $data['commerce_promotion_usage']['order_id'] = [
    'title' => t('Order'),
    'help' => t('The order.'),
    'relationship' => [
      'base' => 'commerce_order',
      'base field' => 'order_id',
      'id' => 'standard',
      'label' => t('Order'),
    ],
    'field' => [
      'id' => 'numeric',
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'numeric',
    ],
    'argument' => [
      'id' => 'standard',
    ],
  ];

  $data['commerce_promotion_usage']['mail'] = [
    'title' => t('Customer email'),
    'help' => t('The customer email.'),
    'relationship' => [
      'base' => 'users_field_data',
      'base field' => 'mail',
      'id' => 'standard',
      'label' => t('User'),
    ],
    'field' => [
      'id' => 'standard',
    ],
    'sort' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'argument' => [
      'id' => 'string',
    ],
  ];
  $data['views']['commerce_coupon_redemption'] = [
    'title' => t('Coupon redemption'),
    'help' => t('Displays a coupon redemption pane, requires an Order ID argument.'),
    'area' => [
      'id' => 'commerce_coupon_redemption',
    ],
  ];

  return $data;
}
