<?php

/**
 * @file
 * Views area plugin info for views_add_button.
 */

/**
 * Implements hook_views_data_alter().
 */
function views_add_button_views_data_alter(array &$data) {
  $data['views']['views_add_button'] = [
    'title' => t('Entity Add Button'),
    'help' => t('Render an add button for an entity.'),
    'area' => ['id' => 'views_add_button_area'],
  ];
  $data['views']['views_add_button_field'] = [
    'title' => t('Entity Add Button'),
    'help' => t('Render an add button for an entity.'),
    'field' => ['id' => 'views_add_button_field'],
  ];
}
