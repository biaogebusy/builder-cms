<?php

/**
 * @file
 * Hooks provided by the Views Data Export module.
 */

/**
 * @addtogroup hooks
 * @{
 */

function hook_views_data_export_row_alter(&$row, \Drupal\views\ResultRow $result, \Drupal\views\ViewExecutable $view) {
  if ($view->id() == 'my_view') {
    $row['custom_field'] = my_function($result['nid']);
  }
}

/**
 * @} End of "addtogroup hooks".
 */