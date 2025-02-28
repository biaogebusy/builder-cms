<?php

namespace Drupal\charts\Plugin\Field\FieldFormatter;

use Drupal\charts\Element\Chart;
use Drupal\Component\Utility\Html;
use Drupal\Core\Field\FieldItemInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;

/**
 * Plugin implementation of the "chart_config_default" formatter.
 *
 * @FieldFormatter(
 *   id = "chart_config_default",
 *   label = @Translation("Default"),
 *   field_types = {
 *     "chart_config",
 *   },
 * )
 */
class ChartConfigItemDefaultFormatter extends FormatterBase {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $elements = [];
    $entity = $items->getEntity();
    $entity_uuid = $entity->uuid();
    $entity_type_id = $entity->getEntityTypeId();
    $bundle = $entity->bundle();
    $chart_id = $entity_type_id . '__' . $bundle;

    foreach ($items as $delta => $item) {
      $id = 'charts-item--' . $entity_uuid . '--' . $delta;
      $elements[$delta] = $this->viewElement($item, $chart_id);
      $elements[$delta]['#id'] = Html::getUniqueId($id);
      $elements[$delta]['#chart_id'] = $chart_id;
    }

    return $elements;
  }

  /**
   * Builds a renderable array for a single chart item.
   *
   * @param \Drupal\Core\Field\FieldItemInterface $item
   *   The chart field item.
   * @param string $chart_id
   *   The chart id.
   *
   * @return array
   *   A renderable array.
   *
   * @throws \Drupal\Core\TypedData\Exception\MissingDataException
   */
  protected function viewElement(FieldItemInterface $item, string $chart_id) {
    $settings = $item->toArray()['config'];
    if ($this->hasData($settings['series']['data_collector_table'])) {
      return Chart::buildElement($settings, $chart_id);
    }
    return [];
  }

  /**
   * Checks if the chart has data.
   *
   * @param array $data_collector_table
   *   The data collector table.
   *
   * @return bool
   *   TRUE if the chart has data, FALSE otherwise.
   */
  protected function hasData(array $data_collector_table) {
    foreach ($data_collector_table as $row) {
      foreach ($row as $cell) {
        if (!empty($cell['data'])) {
          return TRUE;
        }
      }
    }
    return FALSE;
  }

}
