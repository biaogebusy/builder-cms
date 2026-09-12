<?php

namespace Drupal\blazy\Hook;

use Drupal\blazy\Internals\Internals;

/**
 * Hook implementations for config.
 *
 * @todo add #[Hook] attribute, and change them to instance class for D11.
 */
class ConfigHooks {

  /**
   * Implements hook_config_schema_info_alter().
   *
   * {@inheritdoc}
   */
  public static function configSchemaInfoAlter(array &$definitions): void {
    // @todo use BlazyManager DI.
    Internals::configSchemaInfoAlter($definitions, 'blazy_base');
  }

}
