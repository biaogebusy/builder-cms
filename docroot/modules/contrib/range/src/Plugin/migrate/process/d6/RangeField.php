<?php

namespace Drupal\range\Plugin\migrate\process\d6;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Get the range field data.
 *
 * @MigrateProcessPlugin(
 *   id = "d6_range_field",
 *   handle_multiples = TRUE
 * )
 */
class RangeField extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   *
   * Get the range field data.
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $return = [];

    foreach ($value as $v) {
      $return[] = [
        'from' => $v['value'],
        'to' => $v['value'],
      ];
    }

    return $return;
  }

}
