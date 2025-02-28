<?php

/**
 * @file
 * Post update hooks for Blazy.
 */

use Drupal\blazy\BlazyDefault;
use Drupal\Core\Config\Entity\ConfigEntityUpdater;
use Drupal\Core\Entity\Display\EntityViewDisplayInterface;
use Drupal\views\ViewEntityInterface;

/**
 * Clear cache to enable CSP module support.
 */
function blazy_post_update_csp_support() {
  // Empty hook to clear caches.
}

/**
 * Changed grid type to string to support Native Grid for field formatters.
 */
function blazy_post_update_schema_formatter_grid_int_to_string(array &$sandbox = []) {
  $config_entity_updater = \Drupal::classResolver(ConfigEntityUpdater::class);

  $callback = function (EntityViewDisplayInterface $display) {
    $needs_save = FALSE;
    foreach ($display->getComponents() as $field_name => &$component) {
      $config = $component['settings'] ?? [];
      if (!isset($config['style'], $config['grid'], $config['grid_small'])) {
        continue;
      }

      foreach (BlazyDefault::gridSettings() as $key => $value) {
        if (isset($config[$key])) {
          $saved_value = $config[$key];
          $component['settings'][$key] = empty($saved_value) ? '' : (string) $saved_value;
          $needs_save = TRUE;
        }
      }

      // Removed old deprecated/ unused formatter settings.
      // @todo Postponed till 3.x.
      // foreach (['breakpoints', 'sizes', 'grid_header'] as $key) {
      // if (isset($config[$key]) && empty($config[$key])) {
      // unset($component['settings'][$key]);
      // $needs_save = TRUE;
      // }
      // }
      if ($needs_save) {
        $display->setComponent($field_name, $component);
      }
    }

    return $needs_save;
  };

  $config_entity_updater->update($sandbox, 'entity_view_display', $callback);
}

/**
 * Changed grid type to string to support Native Grid for Views styles.
 */
function blazy_post_update_schema_view_grid_int_to_string(array &$sandbox = []) {
  if (!\Drupal::moduleHandler()->moduleExists('views')) {
    return;
  }

  $config_entity_updater = \Drupal::classResolver(ConfigEntityUpdater::class);

  $callback = function (ViewEntityInterface $view) {
    $needs_save = FALSE;
    $deps = $view->getDependencies() ?: [];

    if (!in_array('blazy', $deps['module'])) {
      return $needs_save;
    }

    foreach ($view->get('display') as &$display) {
      $style = $display['display_options']['style'] ?? [];

      if (!isset($style['options'])) {
        continue;
      }

      $config = $style['options'];
      if (!isset($config['style'], $config['grid'], $config['grid_small'])) {
        continue;
      }

      foreach (BlazyDefault::gridSettings() as $key => $value) {
        if (isset($config[$key])) {
          $saved_value = $config[$key];
          $display['display_options']['style']['options'][$key] = empty($saved_value) ? '' : (string) $saved_value;
          $needs_save = TRUE;
        }
      }
    }

    // Looks like ConfigEntityUpdater::update failed with View, manuallly save.
    if ($needs_save) {
      $view->save();
    }

    return $needs_save;
  };

  $config_entity_updater->update($sandbox, 'view', $callback);
}

/**
 * Clear cache to re-generate assets.
 */
function blazy_post_update_vanilla_once() {
  // Empty hook to clear caches.
}

/**
 * Removed io.enabled settings as per #3258851.
 */
function blazy_post_update_remove_io_enabled_key() {
  $config = \Drupal::configFactory()->getEditable('blazy.settings');
  $config->clear('decode');
  $config->clear('io.enabled');
  $config->save(TRUE);
}

/**
 * Fixed for D8 to D10+ cross-compat `app.root`.
 */
function blazy_post_update_app_root() {
  // Empty hook to clear caches.
}

/**
 * Moved media-related classes and services into \Drupal\blazy\Media namespace.
 */
function blazy_post_update_move_media_services_classes() {
  // Empty hook to clear caches.
}

/**
 * Added \Drupal\blazy\BlazyBase service for non-media methods.
 */
function blazy_post_update_added_blazy_base_service() {
  // Empty hook to clear caches.
}

/**
 * Clear cache to enable \Drupal\blazy\BlazyBase service.
 */
function blazy_post_update_implemented_blazy_base_service() {
  // Empty hook to clear caches.
}

/**
 * Added a new Blazy Grid formatter for entityreferences.
 */
function blazy_post_update_added_formatter_blazy_entity() {
  // Empty hook to clear caches.
}

/**
 * Added a new Blazy Media service.
 */
function blazy_post_update_added_blazy_media_service() {
  // Empty hook to clear caches.
}

/**
 * Removed file.repository service from blazy.svg for D9.3, see #3393539.
 */
function blazy_post_update_remove_file_repository_service() {
  // Empty hook to clear caches.
}
