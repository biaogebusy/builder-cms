<?php

/**
 * @file
 * Views specific hooks.
 */

/**
 * Implements hook_views_data().
 */
function entity_print_views_views_data() {
  $data['views']['area_entity_print_views'] = [
    'title' => t('Print'),
    'help' => t('Print the View'),
    'area' => [
      'id' => 'entity_print_views_link',
    ],
  ];
  return $data;
}
