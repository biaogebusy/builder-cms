<?php

/**
 * @file
 * Post update functions for Views Data Export.
 */

use Drupal\Core\Config\Entity\ConfigEntityUpdater;
use Drupal\views\ViewEntityInterface;
use Drupal\views_data_export\Plugin\views\display\DataExport;

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

/**
 * No-op.
 */
function views_data_export_post_update_remap_display_filesystem(?array &$sandbox = NULL): void {
  // This code was insufficient, and we have re-worked it in:
  // views_data_export_post_update_remap_display_filesystem_redux() which is
  // idempotent and so while we need to leave this post-update hook around, we
  // do not need it to do anything.
}

/**
 * Update views displays to use export_filesystem config.
 *
 * The update in views_data_export_post_update_remap_display_filesystem() only
 * handled displays that were explicitly using the 'data_export' display plugin.
 * But that does not cover any display plugins that subclass our display
 * handler. This update re-runs the same logic but checks for the display
 * handler class to check for a subclass.
 */
function views_data_export_post_update_remap_display_filesystem_redux(?array &$sandbox = NULL): void {
  \Drupal::classResolver(ConfigEntityUpdater::class)->update($sandbox, 'view', function (ViewEntityInterface $view): bool {
    $changed = FALSE;
    $displays = $view->get('display') ?? [];

    foreach ($displays as $display_id => $display) {

      // Get an instance of the relevant display plugin.
      $executable = $view->getExecutable();
      $executable->setDisplay($display_id);
      $display_instance_plugin = $executable->getDisplay();

      if (!($display_instance_plugin instanceof DataExport)) {
        continue;
      }

      $options = $display['display_options'] ?? [];

      // We may have already processed this view, so check to see if there's an
      // 'export_filesystem' already set.
      if (isset($options['export_filesystem'])) {
        continue;
      }

      $options['export_filesystem'] = 'private';
      if (array_key_exists('store_in_public_file_directory', $options)) {
        // If the old key was null, that means the private filesystem did not
        // exist when then view was last saved. Set it to be public to be more
        // explicit about the behaviour.
        if ($options['store_in_public_file_directory'] === NULL || !empty($options['store_in_public_file_directory'])) {
          $options['export_filesystem'] = 'public';
        }
        unset($options['store_in_public_file_directory']);
      }
      // If the old key didn't exist, default to private if available.
      else {
        $streamWrapperManager = \Drupal::service('stream_wrapper_manager');
        if (!$streamWrapperManager->isValidScheme('private')) {
          $options['export_filesystem'] = 'public';
        }
      }
      $changed = TRUE;

      $displays[$display_id]['display_options'] = $options;
    }

    if ($changed) {
      $view->set('display', $displays);
    }

    return $changed;
  });
}
