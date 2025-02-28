<?php

namespace Drupal\entity_print\Plugin;

use Drupal\Component\Plugin\PluginManagerInterface;

/**
 * Print plugin manager interface.
 */
interface EntityPrintPluginManagerInterface extends PluginManagerInterface {

  /**
   * Gets currently selected plugin for this export type.
   *
   * @param string $export_type
   *   The export type plugin id.
   *
   * @return \Drupal\entity_print\Plugin\PrintEngineInterface
   *   The loaded print engine.
   */
  public function createSelectedInstance($export_type);

  /**
   * Checks if a plugin is enabled based on its dependencies.
   *
   * @param string $plugin_id
   *   The plugin id to check.
   *
   * @return bool
   *   TRUE if the plugin is disabled otherwise FALSE.
   */
  public function isPrintEngineEnabled($plugin_id);

  /**
   * Gets all disabled print engine definitions.
   *
   * @param string $filter_export_type
   *   The export type you want to filter by.
   *
   * @return array
   *   An array of disabled print engine definitions.
   */
  public function getDisabledDefinitions($filter_export_type);

}
