<?php


/**
 * Udpate views date field custom formart with strip_tags.
 */
function xinshi_api_post_update_views_date_field_strip_tags() {
  $views_list = \Drupal\views\Views::getViewsAsOptions();
  foreach ($views_list as $name => $view) {
    $vid = explode(':', $name)[0];
    $display_id = explode(':', $name)[1];
    /** @var \Drupal\views\ViewExecutable $view */
    $view = \Drupal\views\Views::getView($vid);
    $view->setDisplay($display_id);
    $display_options = &$view->getDisplay()->options;
    $changed = false;
    foreach ($display_options['fields'] as $field_name => &$field_info) {
      if (isset($field_info['settings']['date_format'])) {
        $field_info['alter']['strip_tags'] = true;
        $field_info['alter']['trim_whitespace'] = true;
        $changed = true;
      }
    }
    if ($changed) {
      $view->getDisplay()->setOption('fields', $display_options['fields']);
      $view->save();
    }
  }
  \Drupal::service('cache.render')->invalidateAll();
  \Drupal::service('cache.data')->invalidateAll();
  \Drupal::service('cache.config')->invalidateAll();
}
