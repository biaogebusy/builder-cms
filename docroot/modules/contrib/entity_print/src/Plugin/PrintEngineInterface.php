<?php

namespace Drupal\entity_print\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Core\Plugin\PluginFormInterface;

/**
 * Print engine interface.
 */
interface PrintEngineInterface extends PluginInspectionInterface, PluginFormInterface, ConfigurableInterface {

  /**
   * Gets the export type.
   *
   * @return \Drupal\entity_print\Plugin\ExportTypeInterface
   *   The export type interface.
   */
  public function getExportType();

  /**
   * Add a string of HTML to a new page.
   *
   * @param string $content
   *   The string of HTML to add to a new page.
   *
   * @return $this
   */
  public function addPage($content);

  /**
   * Send the Print contents to the browser.
   *
   * @param string $filename
   *   The filename if we want to force the browser to download.
   * @param bool $force_download
   *   TRUE to attempt to force the browser to download the PDF otherwise FALSE.
   *
   * @throws \Drupal\entity_print\PrintEngineException
   *   Thrown when Print generation fails.
   */
  public function send($filename, $force_download = TRUE);

  /**
   * Gets the binary data for the printed document.
   *
   * @return mixed
   *   The binary data.
   */
  public function getBlob();

  /**
   * Checks if the Print engine dependencies are available.
   *
   * @return bool
   *   TRUE if this implementation has its dependencies met otherwise FALSE.
   */
  public static function dependenciesAvailable();

  /**
   * Gets the installation instructions for this Print engine.
   *
   * @return string
   *   A description of how the user can meet the dependencies for this engine.
   */
  public static function getInstallationInstructions();

  /**
   * Gets the object for this Print engine.
   *
   * Note, it is not advised that you use this method if you want your code to
   * work generically across all print engines.
   *
   * @return object
   *   The implementation specific print object being used.
   */
  public function getPrintObject();

}
