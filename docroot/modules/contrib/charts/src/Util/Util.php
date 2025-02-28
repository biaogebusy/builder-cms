<?php

namespace Drupal\charts\Util;

use Drupal\views\ViewExecutable;

/**
 * Utilities class containing various helper methods.
 */
class Util {

  /**
   * Views Data.
   *
   * @param \Drupal\views\ViewExecutable|null $view
   *   View.
   * @param array $labelValues
   *   Label Values.
   * @param string $labelField
   *   Label Field.
   * @param array $color
   *   Colors.
   * @param string|null $attachmentChartTypeOption
   *   Attachment Chart Type Option.
   *
   * @return array
   *   Data.
   */
  public static function viewsData(ViewExecutable $view = NULL, array $labelValues = [], $labelField = '', array $color = [], $attachmentChartTypeOption = NULL) {
    $data = [];
    $style_options = $view->getStyle()->options['chart_settings'];
    foreach ($view->result as $row_number => $row) {
      $view->row_index = $row->index;
      $numberFields = 0;
      $rowData = [];
      foreach ($labelValues as $fieldId => $rowDataValue) {
        if ($style_options['fields']['allow_advanced_rendering'] == 1 || isset($view->field[$labelField]->options['type']) && $view->field[$labelField]->options['type'] === 'timestamp') {
          $renderedLabelField = $view->field[$labelField]->advancedRender($row);
        }
        else {
          $renderedLabelField = $view->field[$labelField]->getValue($row);
        }
        $renderedLabelField = strip_tags($renderedLabelField);
        $rowData[$numberFields] = [
          'value' => $style_options['fields']['allow_advanced_rendering'] ? $view->field[$fieldId]->advancedRender($row) : $view->field[$fieldId]->getValue($row),
          'label_field' => $renderedLabelField,
          'label' => $view->field[$fieldId]->label(),
          'color' => $color[$fieldId]['color'],
          'type' => $attachmentChartTypeOption,
        ];
        $numberFields++;
      }
      $data[$row_number] = $rowData;
    }

    return $data;
  }

  /**
   * Removes unselected fields.
   *
   * @param array $valueField
   *   Value Field.
   *
   * @return array
   *   Field Values.
   */
  public static function removeUnselectedFields(array $valueField = []) {
    $fieldValues = [];
    foreach ($valueField as $key => $value) {
      if (!empty($value)) {
        $fieldValues[$key] = $value;
      }
    }
    return $fieldValues;
  }

  /**
   * Remove hidden fields.
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The view.
   * @param array $fieldValues
   *   Field values.
   *
   * @return array
   *   Visible views.
   */
  public static function removeHiddenFields(ViewExecutable $view, array $fieldValues) {
    $fields = $view->display_handler->getOption('fields');
    $visibleFields = array_filter($fields, function ($field) {
      return !empty($field['exclude']);
    });
    $visibleFields = array_diff_key($fieldValues, $visibleFields);

    return $visibleFields;
  }

  /**
   * Creates chart data to be used later by visualization frameworks.
   *
   * @param array $data
   *   Data.
   *
   * @return array
   *   Chart Data.
   */
  public static function createChartableData(array $data = []) {
    $chartData = [];
    $categories = [];
    $seriesData = [];

    for ($i = 0; $i < count($data[0]); $i++) {

      $seriesRowData = [
        'name' => '',
        'color' => '',
        'type' => '',
        'data' => [],
      ];
      for ($j = 0; $j < count($data); $j++) {
        $categories[$j] = $data[$j][$i]['label_field'];
        $seriesRowData['name'] = $data[$j][$i]['label'];
        $seriesRowData['type'] = $data[$j][$i]['type'];
        $seriesRowData['color'] = $data[$j][$i]['color'];
        array_push($seriesRowData['data'], (json_decode(($data[$j][$i]['value']))));
      }
      array_push($seriesData, $seriesRowData);
    }
    $chartData[0] = $categories;
    $chartData[1] = $seriesData;

    return $chartData;
  }

  /**
   * Checks for missing libraries necessary for data visualization.
   *
   * @param string $libraryPath
   *   Library Path.
   */
  public static function checkMissingLibrary($libraryPath = '') {
    if (!file_exists(DRUPAL_ROOT . DIRECTORY_SEPARATOR . $libraryPath)) {
      \Drupal::service('messenger')
        ->addMessage(t('Charting libraries might not be installed at the location @libraryPath.', [
          '@libraryPath' => $libraryPath,
        ]), 'error');
    }
  }

}
