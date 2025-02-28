<?php

namespace Drupal\range\Plugin\migrate\process\d7;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Get the range field instance settings.
 *
 * @MigrateProcessPlugin(
 *   id = "d7_range_field_instance_settings",
 *   handle_multiples = TRUE
 * )
 */
class RangeFieldInstanceSettings extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   *
   * Get the range field instance settings.
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    [$instance_settings] = $value;

    // FROM/TO labels are moved to the widget settings.
    unset($instance_settings['from']['label'], $instance_settings['to']['label']);

    // Filter out any other settings, not present in the new module version.
    $allowed_settings = [
      'max' => NULL,
      'min' => NULL,
      'field' => NULL,
      'from' => NULL,
      'to' => NULL,
      'combined' => NULL,
    ];
    return array_intersect_key($instance_settings, $allowed_settings);
  }

}
