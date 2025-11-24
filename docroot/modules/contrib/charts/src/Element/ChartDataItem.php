<?php

namespace Drupal\charts\Element;

use Drupal\Core\Render\Element\RenderElementBase;

/**
 * Provides a chart data item render element.
 *
 * @RenderElement("chart_data_item")
 */
class ChartDataItem extends RenderElementBase {

  /**
   * {@inheritdoc}
   */
  public function getInfo(): array {
    return [
      '#data' => NULL,
      '#color' => NULL,
      // Often used as content of the tooltip.
      '#title' => NULL,
    ];
  }

}
