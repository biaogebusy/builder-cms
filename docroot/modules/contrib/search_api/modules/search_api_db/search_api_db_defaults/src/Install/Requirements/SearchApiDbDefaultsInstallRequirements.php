<?php

namespace Drupal\search_api_db_defaults\Install\Requirements;

use Drupal\Core\Extension\InstallRequirementsInterface;

/**
 * Checks installation requirements for the Database Search Defaults module.
 */
class SearchApiDbDefaultsInstallRequirements implements InstallRequirementsInterface {

  /**
   * {@inheritdoc}
   */
  public static function getRequirements(): array {
    if (!function_exists('search_api_db_defaults_requirements')) {
      require_once __DIR__ . '/../../../search_api_db_defaults.install';
    }
    return search_api_db_defaults_requirements('install');
  }

}
