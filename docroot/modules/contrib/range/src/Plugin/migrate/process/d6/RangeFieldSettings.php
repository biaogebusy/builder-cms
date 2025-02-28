<?php

namespace Drupal\range\Plugin\migrate\process\d6;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Get the range field settings.
 *
 * @MigrateProcessPlugin(
 *   id = "d6_range_field_settings",
 *   handle_multiples = TRUE
 * )
 */
class RangeFieldSettings extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   *
   * Get the range field settings.
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    [$field_type, $global_settings] = $value;

    if ($field_type === 'range_decimal') {
      return [
        'precision' => $global_settings['precision'],
        'scale' => $global_settings['scale'],
      ];
    }
    else {
      return [];
    }
  }

}
