<?php

namespace Drupal\charts;

use Drupal\Component\Serialization\Yaml;
use Drupal\Component\Utility\Color;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Manage the updates and upgrades of settings.
 */
class ConfigUpdater implements ContainerInjectionInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The module extension list.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected $moduleExtensionList;

  /**
   * ConfigEntityUpdater constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Extension\ModuleExtensionList $extension_list_module
   *   The module extension list.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, ModuleHandlerInterface $module_handler, ModuleExtensionList $extension_list_module) {
    $this->entityTypeManager = $entity_type_manager;
    $this->moduleHandler = $module_handler;
    $this->moduleExtensionList = $extension_list_module;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('module_handler'),
      $container->get('extension.list.module')
    );
  }

  /**
   * Transforms legacy settings to newer setting architecture.
   *
   * @param array $old_settings
   *   The old settings.
   * @param string $for
   *   The configuration for which the transformation is being done.
   *    Regular config or view.
   *
   * @return array
   *   The new format settings.
   */
  public function transformVersion3SettingsToNew(array &$old_settings, string $for = 'config') {
    $new_settings = [];
    $new_settings['fields']['stacking'] = !empty($old_settings['grouping']);
    $old_config_keys = $this->getLegacySettingsMappingKeys();
    foreach ($old_settings as $setting_id => $setting_value) {
      if (empty($old_config_keys[$setting_id])) {
        continue;
      }

      $setting_key_map = $old_config_keys[$setting_id];
      $value = $this->transformBoolStringValueToBool($setting_value);
      // When a block setting belongs to the chart blocks we save it in a
      // new setting.
      if (substr($setting_key_map, 0, 7) === 'display') {
        // Stripping the 'display_' in front of the mapping key.
        $setting_key_map = substr($setting_key_map, 8, strlen($setting_key_map));
        if (substr($setting_key_map, 0, 10) === 'dimensions') {
          // Stripping dimensions_.
          $setting_key_map = substr($setting_key_map, 11, strlen($setting_key_map));
          $new_settings['display']['dimensions'][$setting_key_map] = $value;
        }
        elseif (substr($setting_key_map, 0, 5) === 'gauge') {
          // Stripping gauge_.
          $setting_key_map = substr($setting_key_map, 6, strlen($setting_key_map));
          $new_settings['display']['gauge'][$setting_key_map] = $value;
        }
        else {
          $new_settings['display'][$setting_key_map] = $value;
        }
      }
      elseif (substr($setting_key_map, 0, 5) === 'xaxis') {
        // Stripping xaxis_.
        $setting_key_map = substr($setting_key_map, 6, strlen($setting_key_map));
        $new_settings['xaxis'][$setting_key_map] = $value;
      }
      elseif (substr($setting_key_map, 0, 5) === 'yaxis') {
        // Stripping yaxis_.
        $setting_key_map = substr($setting_key_map, 6, strlen($setting_key_map));
        if (substr($setting_key_map, 0, 9) === 'secondary') {
          // Stripping gauge_.
          $setting_key_map = substr($setting_key_map, 10, strlen($setting_key_map));
          $new_settings['yaxis']['secondary'][$setting_key_map] = $value;
        }
        else {
          $new_settings['yaxis'][$setting_key_map] = $value;
        }
      }
      elseif (substr($setting_key_map, 0, 6) === 'fields') {
        // Stripping fields_.
        $setting_key_map = substr($setting_key_map, 7, strlen($setting_key_map));
        if ($setting_key_map === 'data_providers' && is_array($value)) {
          $data_providers = $new_settings['fields']['data_providers'] ?? [];
          if ($setting_id === 'data_fields' || $setting_id == 'field_colors') {
            $new_settings['fields']['data_providers'] = $this->transformLegacyFieldsDataProvidersToNew($data_providers, $value);
          }
        }
        else {
          $new_settings['fields'][$setting_key_map] = $value;
        }
      }
      elseif ($setting_key_map === 'grouping' && $new_settings['fields']['stacking']) {
        $new_settings[$setting_key_map] = [];
      }
      else {
        // We make sure that we handle the color unneeded array.
        $new_settings[$setting_key_map] = $setting_key_map !== 'color' ? $value : $value[0];
      }
      // Then we remove it from the main old settings tree.
      unset($old_settings[$setting_id]);
    }

    // Allow other modules to alter the new settings.
    $this->moduleHandler->alter('charts_version3_to_new_settings_structure', $new_settings, $for, $this);
    return $new_settings;
  }

  /**
   * Initialize the current default settings.
   */
  public function initializedCurrentDefaultSettings() {
    $path = $this->moduleExtensionList->getPath('charts');
    $default_install_settings_file = $path . '/config/install/charts.settings.yml';
    $default_install_settings = Yaml::decode(file_get_contents($default_install_settings_file));

    $new_settings = &$default_install_settings['charts_default_settings'];

    // Allow other modules to alter the new settings.
    $for = 'config';
    $this->moduleHandler->alter('charts_version3_to_new_settings_structure', $new_settings, $for, $this);
    return $default_install_settings;
  }

  /**
   * Updates settings from version 3 of views.
   */
  public function updateExistingViewsVersion3ToNewSettings() {
    $view_storage = $this->entityTypeManager->getStorage('view');
    $view_ids = $view_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('display.*.display_options.style.type', 'chart', '=')
      ->execute();

    if (!$view_ids) {
      return 'Views: No views had a display set to a charts style.';
    }

    $updated_views = [];
    foreach ($view_ids as $view_id) {
      /** @var \Drupal\views\ViewEntityInterface $view */
      if (!($view = $view_storage->load($view_id))) {
        continue;
      }

      $changed = FALSE;
      $displays = $view->get('display');
      foreach ($displays as &$display) {
        $style = &$display['display_options']['style'];
        if ($style['type'] !== 'chart' || !isset($style['options']['field_colors']) || !isset($style['options']['fields']['table'])) {
          continue;
        }

        $changed = TRUE;
        // Removing this because it was set in version 3 but was not used for
        // anything.
        unset($style['options']['fields']);

        $options = &$style['options'];
        $options = $this->transformVersion3SettingsToNew($options, 'view');
        $chart_settings_elements = [
          'library',
          'type',
          'fields',
          'display',
          'xaxis',
          'yaxis',
        ];
        foreach ($options as $option_key => $option) {
          if (in_array($option_key, $chart_settings_elements)) {
            $options['chart_settings'][$option_key] = $option;
            unset($options[$option_key]);
          }
        }
      }
      if ($changed) {
        $view->set('display', $displays);
        $view->save();
        $updated_views[] = $view_id;
      }
    }

    if ($updated_views) {
      return sprintf('Views: The following views were updated: %s', implode(', ', $updated_views));
    }
    return sprintf('Views: The following views(%s) with at least one display of charts style were loaded but not updated!', implode(', ', array_values($view_ids)));
  }

  /**
   * Transforms boolean string value to real boolean.
   *
   * @param mixed $value
   *   The value to be transformed.
   *
   * @return bool|mixed
   *   The boolean value or the original passed value.
   */
  public function transformBoolStringValueToBool($value) {
    if ($value === 'FALSE' || $value === 'false') {
      return FALSE;
    }
    elseif ($value === 'TRUE' || $value === 'true') {
      return TRUE;
    }
    return $value;
  }

  /**
   * Transforms legacy fields data providers to new.
   *
   * @param array $data_providers
   *   Data providers.
   * @param array $legacy_value
   *   Legacy value.
   *
   * @return mixed
   *   Data providers returned
   */
  private function transformLegacyFieldsDataProvidersToNew(array $data_providers, array $legacy_value) {
    $default_weight = 0;
    foreach ($legacy_value as $field_id => $value) {
      if (Color::validateHex($value)) {
        $data_providers[$field_id]['color'] = $value;
      }
      else {
        $data_providers[$field_id]['enabled'] = !empty($value);
      }
      $data_providers[$field_id]['weight'] = $default_weight;
      $default_weight++;
    }
    return $data_providers;
  }

  /**
   * Gets legacy settings mapping keys.
   *
   * @return array
   *   Legacy settings keys to newer ones mapping.
   */
  private function getLegacySettingsMappingKeys() {
    return [
      'library' => 'library',
      'chart_library' => 'library',
      'type' => 'type',
      'chart_type' => 'type',
      'grouping' => 'grouping',
      'title' => 'display_title',
      'title_position' => 'display_title_position',
      'data_labels' => 'display_data_labels',
      'data_markers' => 'display_data_markers',
      'legend' => 'display_legend',
      'legend_position' => 'display_legend_position',
      'background' => 'display_background',
      'three_dimensional' => 'display_three_dimensional',
      'polar' => 'display_polar',
      'series' => 'series',
      'data' => 'data',
      'color' => 'color',
      'data_series' => 'data_series',
      'series_label' => 'series_label',
      'categories' => 'categories',
      'field_colors' => 'fields_data_providers',
      'tooltips' => 'display_tooltips',
      'tooltips_use_html' => 'display_tooltips_use_html',
      'width' => 'display_dimensions_width',
      'height' => 'display_dimensions_height',
      'width_units' => 'display_dimensions_width_units',
      'height_units' => 'display_dimensions_height_units',
      'colors' => 'display_colors',
      'xaxis_title' => 'xaxis_title',
      'xaxis_labels_rotation' => 'xaxis_labels_rotation',
      'yaxis_title' => 'yaxis_title',
      'yaxis_min' => 'yaxis_min',
      'yaxis_max' => 'yaxis_max',
      'yaxis_prefix' => 'yaxis_prefix',
      'yaxis_suffix' => 'yaxis_suffix',
      'yaxis_decimal_count' => 'yaxis_decimal_count',
      'yaxis_labels_rotation' => 'yaxis_labels_rotation',
      'inherit_yaxis' => 'yaxis_inherit',
      'secondary_yaxis_title' => 'yaxis_secondary_title',
      'secondary_yaxis_min' => 'yaxis_secondary_min',
      'secondary_yaxis_max' => 'yaxis_secondary_min',
      'secondary_yaxis_prefix' => 'yaxis_secondary_prefix',
      'secondary_yaxis_suffix' => 'yaxis_secondary_suffix',
      'secondary_yaxis_decimal_count' => 'yaxis_secondary_decimal_count',
      'secondary_yaxis_labels_rotation' => 'yaxis_secondary_labels_rotation',
      'green_from' => 'display_gauge_green_from',
      'green_to' => 'display_gauge_green_to',
      'red_from' => 'display_gauge_red_from',
      'red_to' => 'display_gauge_red_to',
      'yellow_from' => 'display_gauge_yellow_from',
      'yellow_to' => 'display_gauge_yellow_to',
      'max' => 'display_gauge_max',
      'min' => 'display_gauge_min',
      'allow_advanced_rendering' => 'fields_allow_advanced_rendering',
      'label_field' => 'fields_label',
      'data_fields' => 'fields_data_providers',
    ];
  }

}
