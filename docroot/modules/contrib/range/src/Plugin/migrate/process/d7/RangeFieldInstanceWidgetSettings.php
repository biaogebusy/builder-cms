<?php

namespace Drupal\range\Plugin\migrate\process\d7;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Get the range field instance widget settings.
 *
 * @MigrateProcessPlugin(
 *   id = "d7_range_field_instance_widget_settings",
 *   handle_multiples = TRUE
 * )
 */
class RangeFieldInstanceWidgetSettings extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   *
   * Get the range field widget settings.
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    return [
      'label' => [
        'from' => $value['from']['label'],
        'to' => $value['to']['label'],
      ],
      'placeholder' => [
        'from' => '',
        'to' => '',
      ],
    ];
  }

}
