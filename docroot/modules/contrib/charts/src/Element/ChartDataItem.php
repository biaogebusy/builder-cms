<?php

namespace Drupal\charts\Element;

use Drupal\Core\Render\Element\RenderElement;

/**
 * Provides a chart data item render element.
 *
 * @RenderElement("chart_data_item")
 */
class ChartDataItem extends RenderElement {

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
