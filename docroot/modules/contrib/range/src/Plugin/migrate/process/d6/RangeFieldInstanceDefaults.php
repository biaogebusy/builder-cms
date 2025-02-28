<?php

namespace Drupal\range\Plugin\migrate\process\d6;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Get the range field instance defaults.
 *
 * @MigrateProcessPlugin(
 *   id = "d6_range_field_instance_defaults",
 *   handle_multiples = TRUE
 * )
 */
class RangeFieldInstanceDefaults extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   *
   * Set the range field instance defaults.
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    [$widget_type, $widget_settings] = $value;
    if (isset($widget_settings['default_value'][0]['value']) && is_numeric($widget_settings['default_value'][0]['value'])) {
      return [
        [
          'from' => $widget_settings['default_value'][0]['value'],
          'to' => $widget_settings['default_value'][0]['value'],
        ],
      ];
    }
    return [];
  }

}
