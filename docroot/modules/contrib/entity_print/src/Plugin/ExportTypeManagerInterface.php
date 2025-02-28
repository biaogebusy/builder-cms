<?php

namespace Drupal\entity_print\Plugin;

use Drupal\Component\Plugin\PluginManagerInterface;

/**
 * Export type manager interface.
 */
interface ExportTypeManagerInterface extends PluginManagerInterface {

  /**
   * Gets an array of options suitable for Form API.
   *
   * @return array
   *   An array with plugin ids as the keys and the label as values.
   */
  public function getFormOptions();

}
