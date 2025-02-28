<?php

namespace Drupal\entity_print\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;

/**
 * Export type plugin interface.
 */
interface ExportTypeInterface extends PluginInspectionInterface {

  /**
   * The export type label.
   *
   * @return string
   *   The label string.
   */
  public function label();

  /**
   * Gets the file extension for the printed document.
   *
   * @return string
   *   The file extension.
   */
  public function getFileExtension();

}
