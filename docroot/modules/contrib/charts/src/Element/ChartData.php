<?php

namespace Drupal\charts\Element;

use Drupal\Core\Render\Element\RenderElementBase;

/**
 * Provides a chart data render element.
 *
 * @RenderElement("chart_data")
 */
class ChartData extends RenderElementBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo(): array {
    return [
      '#title' => NULL,
      '#labels' => NULL,
      '#data' => [],
      '#color' => NULL,
      '#show_in_legend' => TRUE,
      // Show inline labels next to the data.
      '#show_labels' => FALSE,
      // If building multiple charts. The chart type, e.g. pie.
      '#chart_type' => NULL,
      // Line chart only.
      '#line_width' => 1,
      // Line chart only. Size in pixels, e.g. 1, 5.
      '#marker_radius' => 3,
      // If using multiple axes, key for the matching y axis.
      '#target_axis' => NULL,
      // Formatting options.
      // The number of digits after the decimal separator. e.g. 2.
      '#decimal_count' => NULL,
      // A custom date format, e.g. %Y-%m-%d.
      '#date_format' => NULL,
      '#prefix' => NULL,
      '#suffix' => NULL,
    ];
  }

}
