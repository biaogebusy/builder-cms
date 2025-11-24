<?php

/**
 * @file
 * Post update functions for Views Data Export.
 */

use Drupal\Core\Config\Entity\ConfigEntityUpdater;
use Drupal\views\ViewEntityInterface;

/**
 * Post update data export views to preserve original XML encoding.
 */
function views_data_export_post_update_xml_encoding(?array &$sandbox = NULL): void {
  \Drupal::classResolver(ConfigEntityUpdater::class)->update($sandbox, 'view', function (ViewEntityInterface $view): bool {

    $changed = FALSE;
    $displays = $view->get('display');

    foreach ($displays as &$display) {
      $display_plugin = $display['display_plugin'] ?? '';
      if ($display_plugin === 'data_export') {
        if (isset($display['display_options']['style']['type']) && $display['display_options']['style']['type'] === 'data_export') {
          if (isset($display['display_options']['style']['options']['formats']) && in_array('xml', $display['display_options']['style']['options']['formats'])) {
            if (!isset($display['display_options']['style']['options']['xml_settings']['encoding'])) {
              // Preserve the original, blank, encoding to maintain backwards
              // compatibility.
              $display['display_options']['style']['options']['xml_settings']['encoding'] = '';
              $changed = TRUE;
            }
          }
        }
      }
    }

    if ($changed) {
      $view->set('display', $displays);
    }

    return $changed;

  });
}
