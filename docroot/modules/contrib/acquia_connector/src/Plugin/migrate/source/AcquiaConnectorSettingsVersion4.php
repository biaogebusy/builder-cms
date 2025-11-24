<?php

namespace Drupal\acquia_connector\Plugin\migrate\source;

use Drupal\migrate\Exception\RequirementsException;
use Drupal\migrate_drupal\Plugin\migrate\source\Variable;

/**
 * Drupal 7 Acquia Connector source from variable table.
 *
 * For additional configuration keys, refer to the parent classes.
 *
 * @see \Drupal\migrate\Plugin\migrate\source\SqlBase
 * @see \Drupal\migrate\Plugin\migrate\source\SourcePluginBase
 *
 * @MigrateSource(
 *   id = "acquia_connector_settings_v4",
 *   source_module = "acquia_agent",
 * )
 */
class AcquiaConnectorSettingsVersion4 extends Variable {

  /**
   * {@inheritdoc}
   */
  public function checkRequirements() {
    parent::checkRequirements();
    if ($this->getModuleSchemaVersion('acquia_agent') < 7004) {
      throw new RequirementsException("Acquia Agent module version incompatible with this migration.");
    }
  }

}
