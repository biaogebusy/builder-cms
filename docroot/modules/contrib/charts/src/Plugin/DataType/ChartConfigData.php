<?php

namespace Drupal\charts\Plugin\DataType;

use Drupal\Core\TypedData\TypedData;

/**
 * Provides a data type wrapping for chart.
 *
 * @DataType(
 *   id = "chart_config",
 *   label = @Translation("Chart config"),
 *   description = @Translation("A chart configuration"),
 * )
 */
class ChartConfigData extends TypedData {

  /**
   * Cached processed value.
   *
   * @var string
   */
  protected $value;

}
