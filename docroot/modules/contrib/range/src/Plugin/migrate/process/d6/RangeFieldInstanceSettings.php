<?php

namespace Drupal\range\Plugin\migrate\process\d6;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Get the range field instance settings.
 *
 * @MigrateProcessPlugin(
 *   id = "d6_range_field_instance_settings",
 *   handle_multiples = TRUE
 * )
 */
class RangeFieldInstanceSettings extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   *
   * Set the field instance settings.
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    [$widget_type, $widget_settings, $field_settings] = $value;
    return [
      'min' => $field_settings['min'] === '<' ? NULL : $field_settings['min'],
      'max' => $field_settings['max'] === '>' ? NULL : $field_settings['max'],
      'field' => [
        'prefix' => $field_settings['prefix'],
        'suffix' => $field_settings['suffix'],
      ],
    ];
  }

}
