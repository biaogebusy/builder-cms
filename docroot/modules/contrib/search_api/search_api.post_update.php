<?php

/**
 * @file
 * Post update functions for Search API.
 */

use Drupal\Core\Config\Entity\ConfigEntityUpdater;

/**
 * Re-save Search API index configurations to fix dependencies.
 */
function search_api_post_update_fix_index_dependencies(&$sandbox = NULL) {
  \Drupal::classResolver(ConfigEntityUpdater::class)
    ->update($sandbox, 'search_api_index', function () {
      // Re-save all search API indexes.
      return TRUE;
    });
}

/**
 * Update Search API views to use the correct query type.
 *
 * In some cases, Views creates Search API views with the default "views_query"
 * query type instead of "search_api_query".
 */
function search_api_post_update_views_query_type() {
  $config_factory = \Drupal::configFactory();
  $changed_cache = [];

  foreach ($config_factory->listAll('views.view.') as $view_config_name) {
    $view = $config_factory->getEditable($view_config_name);
    if (substr($view->get('base_table'), 0, 17) == 'search_api_index_') {
      $displays = $view->get('display');

      $update_query = $update_cache = FALSE;
      foreach ($displays as $id => $display) {
        if (($display['display_options']['query']['type'] ?? '') === 'views_query') {
          $displays[$id]['display_options']['query']['type'] = 'search_api_query';
          $update_query = TRUE;
        }
        if (in_array($display['display_options']['cache']['type'] ?? '', ['tag', 'time'])) {
          $displays[$id]['display_options']['cache']['type'] = 'none';
          $update_cache = TRUE;
        }
      }

      if ($update_query || $update_cache) {
        $view->set('display', $displays);
        // Mark the resulting configuration as trusted data. This avoids issues
        // with future schema changes.
        $view->save(TRUE);
        if ($update_cache) {
          $changed_cache[] = $view->get('id');
        }
      }
    }
  }

  if ($changed_cache) {
    $vars = ['@ids' => implode(', ', array_unique($changed_cache))];
    return t('The following views have had caching switched off. The selected caching mechanism does not work with views on Search API indexes. Please either use one of the Search API-specific caching options or "None": @ids.', $vars);
  }

  return NULL;
}
