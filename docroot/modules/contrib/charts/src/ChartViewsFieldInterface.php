<?php

namespace Drupal\charts;

/**
 * Defines the interface for the chart views field data type.
 */
interface ChartViewsFieldInterface {

  /**
   * Get the chart field data type.
   *
   * @return string
   *   The chart field data type.
   */
  public function getChartFieldDataType(): string;

}
