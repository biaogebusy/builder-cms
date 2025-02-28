<?php

namespace Drupal\entity_print\Plugin\EntityPrint\ExportType;

use Drupal\Core\Plugin\PluginBase;
use Drupal\entity_print\Plugin\ExportTypeInterface;

/**
 * Export type plugin class.
 */
class DefaultExportType extends PluginBase implements ExportTypeInterface {

  /**
   * {@inheritdoc}
   */
  public function label() {
    return $this->getPluginDefinition()['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFileExtension() {
    return $this->getPluginDefinition()['file_extension'];
  }

}
