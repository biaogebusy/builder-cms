<?php

namespace Drupal\charts\Plugin\chart\Library;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\PluginFormInterface;

/**
 * Defines an interface for Chart plugins.
 */
interface ChartInterface extends PluginInspectionInterface, PluginFormInterface, ConfigurableInterface {

  /**
   * Used to define a single axis.
   *
   * Constant used in chartsTypeInfo() to declare chart types with a
   * single axis. For example a pie chart only has a single dimension.
   */
  const SINGLE_AXIS = 'y_only';

  /**
   * Used to define a dual axis.
   *
   * Constant used in chartsTypeInfo() to declare chart types with a dual
   * axes. Most charts use this type of data, meaning multiple categories each
   * have multiple values. This type of data is usually represented as a table.
   */
  const DUAL_AXIS = 'xy';

  /**
   * Pre render.
   *
   * @param array $element
   *   The element.
   *
   * @return array
   *   The chart element.
   */
  public function preRender(array $element);

  /**
   * Return the name of the chart.
   *
   * @return string
   *   Returns the name as a string.
   */
  public function getChartName();

  /**
   * Gets the supported chart types.
   *
   * @return array
   *   The supported chart types.
   */
  public function getSupportedChartTypes();

  /**
   * Checks if a chart type is supported.
   *
   * @param string $chart_type_id
   *   The chart type ID.
   *
   * @return bool
   *   TRUE if the chart type is supported, FALSE otherwise.
   */
  public function isSupportedChartType(string $chart_type_id);

}
