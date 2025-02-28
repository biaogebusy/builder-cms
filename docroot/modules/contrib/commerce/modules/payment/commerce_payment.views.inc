<?php

/**
 * @file
 * Provide views data for the Payment module.
 */

/**
 * Implements hook_views_data().
 */
function commerce_payment_views_data() {
  $data['views']['commerce_payment_total_summary'] = [
    'title' => t('Payment total summary'),
    'area' => [
      'id' => 'commerce_payment_total_summary',
    ],
  ];
  return $data;
}
