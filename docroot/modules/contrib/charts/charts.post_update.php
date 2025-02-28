<?php

/**
 * @file
 * Charts post-update file.
 */

use Drupal\charts\ConfigUpdater;
use Drupal\Core\Serialization\Yaml;

/**
 * Initialize advanced requirements cdn config value.
 */
function charts_post_update_initialize_advanced_requirements_cdn(&$sandbox) {
  $config = \Drupal::service('config.factory')
    ->getEditable('charts.settings');

  if ($config) {
    $config->set('advanced', ['requirements' => ['cdn' => TRUE]]);
    $config->save();
    return 'Successfully initialized the advanced.requirements.cdn option.';
  }
  return 'No initialization was done. Please check your current config.';
}

/**
 * Update the existing default config display colors to increase from 10 to 25.
 */
function charts_post_update_existing_default_colors_to_twenty_five(&$sandbox) {
  $config = \Drupal::service('config.factory')->getEditable('charts.settings');
  $existing_colors = $config->get('charts_default_settings.display.colors');

  if (is_countable($existing_colors) && count($existing_colors) === 10) {
    $path = \Drupal::service('extension.list.module')->getPath('charts');
    $default_install_settings_file = $path . '/config/install/charts.settings.yml';
    if (!file_exists($default_install_settings_file)) {
      return 'Failed to update the default colors to 25 because we could not load the charts settings.';
    }

    $default_install_settings = Yaml::decode(file_get_contents($default_install_settings_file));
    $install_colors = $default_install_settings['charts_default_settings']['display']['colors'];
    // We only want to add the last 15 colors to make it 25.
    $colors = array_merge($existing_colors, array_slice($install_colors, -15));
    $config->set('charts_default_settings.display.colors', $colors);
    $config->save();
    return 'Successfully increased the number of default colors to 25.';
  }
  return 'We were unable to load existing colors in order to increased them to 25.';
}

/**
 * Initialize the config value for the debug setting.
 */
function charts_post_update_initialize_debug_option(&$sandbox) {
  $config = \Drupal::service('config.factory')
    ->getEditable('charts.settings');

  if ($config) {
    $config->set('advanced.debug', FALSE);
    $config->save();
    return 'Successfully initialized the advanced.debug option.';
  }
  return 'No initialization was done. Please check your current config.';
}

/**
 * Migrate library default settings to library_config key.
 */
function charts_post_update_migrate_library_default_settings_to_library_config(&$sandbox) {
  /** @var \Drupal\Core\Config\ConfigFactoryInterface $config_factory */
  $config_factory = \Drupal::service('config.factory');
  $config = $config_factory->getEditable('charts.settings');

  if ($config) {
    $default_settings = $config->get('charts_default_settings');
    $library_id = $default_settings['library'] ?? '';
    $old_key = $library_id ? 'charts_default_settings.' . $library_id . '_settings' : '';
    if ($old_key && empty($default_settings['display']) && $library_config = $config->get($old_key)) {
      $config->set('charts_default_settings.library_config', $library_config);
      $config->clear($old_key);
      $config->save();
      return 'Library default settings have been successfully migrated to library_config key.';
    }
  }
  return 'No migration was done';
}

/**
 * Clear library_config set on the wrong place. See drupal.org/i/3310145.
 */
function charts_post_update_clear_library_config_key_added_on_wrong_place(&$sandbox) {
  /** @var \Drupal\Core\Config\ConfigFactoryInterface $config_factory */
  $config_factory = \Drupal::service('config.factory');
  $config = $config_factory->getEditable('charts.settings');
  if ($config && $config->get('library_config')) {
    // Not to be confused with "charts_default_settings.library_config" which is
    // the proper key.
    $config->clear('library_config');
    $config->save();
    return 'Successfully cleared the library_config key.';
  }
  return 'The library_config key was not cleared, probably because it was not set.';
}

/**
 * Updates charts config and views with charts style of version 3 to new config.
 */
function charts_post_update_00_update_charts_version_3_to_latest_settings_structure(&$sandbox) {
  /** @var \Drupal\Core\Config\ConfigFactoryInterface $config_factory */
  $config_factory = \Drupal::service('config.factory');
  $config = $config_factory->getEditable('charts.settings');
  $default_settings = $config ? $config->get('charts_default_settings') : [];

  /** @var \Drupal\charts\ConfigUpdater $config_updater */
  $config_updater = \Drupal::classResolver(ConfigUpdater::class);
  if ($default_settings && !isset($default_settings['display'])) {
    $results = [];

    $config->set('charts_default_settings', $config_updater->transformVersion3SettingsToNew($default_settings));
    $config->save();
    $results[] = 'Default Config: Migration to the new structure successfully done.';

    $results[] = $config_updater->updateExistingViewsVersion3ToNewSettings();
    return implode(' -- ', $results);
  }
  elseif (!$default_settings || !$config->getRawData()) {
    $config->setData($config_updater->initializedCurrentDefaultSettings());
    $config->save();

    $results = [];
    $results[] = 'Default Config: No config were found and the default one was saved.';
    $results[] = $config_updater->updateExistingViewsVersion3ToNewSettings();
    return implode(' -- ', $results);
  }
  return 'No update of the settings structure was done.';
}

/**
 * Re-save views that are using the chart style to recalculate dependencies.
 */
function charts_post_update_resave_views_to_account_calculated_library_dependencies(&$sandbox) {
  $view_storage = \Drupal::entityTypeManager()->getStorage('view');
  $view_ids = $view_storage->getQuery()
    ->accessCheck(FALSE)
    ->condition('display.*.display_options.style.type', 'chart', '=')
    ->execute();
  $labels = [];
  if ($view_ids) {
    foreach ($view_ids as $view_id) {
      /** @var \Drupal\views\ViewEntityInterface $view */
      $view = $view_storage->load($view_id);
      // Re-save the view to update its dependencies.
      $view->save();
      $labels[] = $view->label();
    }
  }

  if ($labels) {
    return t('The following views have been re-saved to re-calculate their dependencies: @views', [
      '@views' => implode(',', $labels),
    ]);
  }
  return t('This site did not have any chart related views.');
}

/**
 * Setting charts config module dependencies based on the selected library.
 */
function charts_post_update_setting_charts_config_module_dependencies(&$sandbox) {
  $config = \Drupal::service('config.factory')
    ->getEditable('charts.settings');
  $settings = $config->get('charts_default_settings');
  $library = $settings['library'] ?? '';
  $type = $settings['type'] ?? '';
  if ($library || $type) {
    /** @var \Drupal\charts\EventSubscriber\ConfigImportSubscriber $config_import_subscriber */
    $config_import_subscriber = \Drupal::service('charts.config_import_subscriber');
    $config->set('dependencies', $config_import_subscriber->calculateDependencies($library, $type))
      ->save();
    return 'Updated chart settings dependencies';
  }
  return 'The default config for charts did not have a library and type set. So no dependencies were set.';
}

/**
 * Move color changer from fields to display.
 */
function charts_post_update_move_views_style_color_changer_from_fields_to_display(&$sandbox) {
  $view_storage = \Drupal::entityTypeManager()->getStorage('view');
  $view_ids = $view_storage->getQuery()
    ->accessCheck(FALSE)
    ->condition('display.*.display_options.style.type', 'chart', '=')
    ->execute();
  $labels = [];
  if ($view_ids) {
    foreach ($view_ids as $view_id) {
      /** @var \Drupal\views\ViewEntityInterface $view */
      $view = $view_storage->load($view_id);
      $changed = FALSE;
      foreach (array_keys($view->get('display')) as $display_id) {
        $display = &$view->getDisplay($display_id);
        if (isset($display['display_options']['style']['options']['chart_settings']['fields']['color_changer'])) {
          $changed = TRUE;
          $display['display_options']['style']['options']['chart_settings']['display']['color_changer'] = !empty($display['display_options']['style']['options']['chart_settings']['fields']['color_changer']);
          unset($display['display_options']['style']['options']['chart_settings']['fields']['color_changer']);
        }
      }
      if ($changed) {
        $view->save();
        $labels[] = $view->label();
      }
    }
  }

  if ($labels) {
    return t('The following views have been updated to move the color changer from fields to display: @views', [
      '@views' => implode(',', $labels),
    ]);
  }
  return t('This site did not have any chart related views.');
}
