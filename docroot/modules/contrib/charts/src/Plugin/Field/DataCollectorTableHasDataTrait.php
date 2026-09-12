<?php

namespace Drupal\charts\Plugin\Field;

/**
 * Provides a trait for checking if a data collector table has data.
 */
trait DataCollectorTableHasDataTrait {

  /**
   * Checks if the chart has data.
   *
   * @param array $data_collector_table
   *   The data collector table.
   *
   * @return bool
   *   TRUE if the chart has data, FALSE otherwise.
   */
  protected static function hasData(array $data_collector_table): bool {
    foreach ($data_collector_table as $row) {
      foreach ($row as $cell) {
        if (isset($cell['data']) && $cell['data'] !== '') {
          return TRUE;
        }
      }
    }
    return FALSE;
  }

}
