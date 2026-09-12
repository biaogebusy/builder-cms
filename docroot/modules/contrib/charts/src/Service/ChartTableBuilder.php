<?php

namespace Drupal\charts\Service;

use Drupal\charts\TypeManager;
use Drupal\Core\Render\Element;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Service to build accessible HTML tables from Chart elements.
 */
class ChartTableBuilder {

  use StringTranslationTrait;

  /**
   * The chart type manager.
   *
   * @var \Drupal\charts\TypeManager
   */
  protected $typeManager;

  /**
   * Constructs a ChartTableBuilder object.
   *
   * @param \Drupal\Core\StringTranslation\TranslationInterface $string_translation
   *   The string translation interface.
   * @param \Drupal\charts\TypeManager $type_manager
   *   The type manager.
   */
  public function __construct(TranslationInterface $string_translation, TypeManager $type_manager) {
    $this->stringTranslation = $string_translation;
    $this->typeManager = $type_manager;
  }

  /**
   * Builds a render array for a table based on a chart element.
   *
   * @param array $element
   *   The chart element.
   *
   * @return array
   *   The table.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function buildTable(array $element): array {
    $chart_type = $element['#chart_type'] ?? 'line';
    $type_definition = $this->typeManager->getDefinition($chart_type, FALSE) ?? [];

    $single_axis = isset($type_definition['axis']) && $type_definition['axis'] === 'y_only';
    $coordinate_axis = !empty($type_definition['coordinate']);

    $xaxis_element = [];
    $yaxis_element = [];
    $series_data = [];

    // Parse the render array children in a single pass.
    foreach (Element::children($element) as $key) {
      $child = $element[$key];
      $type = $child['#type'] ?? '';

      if ($type === 'chart_xaxis') {
        $xaxis_element = $child;
      }
      elseif ($type === 'chart_yaxis' && empty($yaxis_element)) {
        $yaxis_element = $child;
      }
      elseif ($type === 'chart_data') {
        $series_data[] = [
          'title' => $child['#title'] ?? $this->t('Series @n', ['@n' => count($series_data) + 1]),
          'data' => $child['#data'] ?? [],
          'labels' => $child['#labels'] ?? [],
        ];
      }
    }

    $categories = $xaxis_element['#labels'] ?? [];

    // Delegate to specific builders to keep cyclomatic complexity low.
    if ($coordinate_axis) {
      [$header, $rows] = $this->buildCoordinateTableData($chart_type, $series_data, $xaxis_element, $yaxis_element, $categories);
    }
    else {
      [$header, $rows] = $this->buildStandardTableData($single_axis, $series_data, $xaxis_element, $categories);
    }

    $chart_title = !empty($element['#title']) ? $element['#title'] : $this->t('Chart');

    return [
      '#type' => 'table',
      '#header' => $header,
      '#rows' => $rows,
      '#attributes' => [
        'class' => ['charts-accessible-table'],
        'role' => 'table',
        'style' => 'width: 100%;',
      ],
      '#caption' => $this->t('Data table for @title', ['@title' => $chart_title]),
      '#sticky' => FALSE,
    ];
  }

  /**
   * Builds header and rows for coordinate-based charts.
   *
   * @param string $chart_type
   *   The chart type.
   * @param array $series_data
   *   The parsed series data.
   * @param array $xaxis_element
   *   The x-axis render element.
   * @param array $yaxis_element
   *   The y-axis render element.
   * @param array $categories
   *   The x-axis category labels.
   *
   * @return array
   *   A numerically indexed array containing the table header and rows.
   */
  protected function buildCoordinateTableData(string $chart_type, array $series_data, array $xaxis_element, array $yaxis_element, array $categories): array {
    $header = [
      ['data' => $this->t('Series'), 'scope' => 'col'],
      ['data' => !empty($xaxis_element['#title']) ? $xaxis_element['#title'] : $this->t('X-Axis'), 'scope' => 'col'],
      ['data' => !empty($yaxis_element['#title']) ? $yaxis_element['#title'] : $this->t('Y-Axis'), 'scope' => 'col'],
    ];

    $has_z_value = in_array($chart_type, ['bubble', 'heatmap'], TRUE);
    if ($chart_type === 'bubble') {
      $header[] = ['data' => $this->t('Size'), 'scope' => 'col'];
    }
    elseif ($chart_type === 'heatmap') {
      $header[] = ['data' => $this->t('Value'), 'scope' => 'col'];
    }

    $rows = [];
    foreach ($series_data as $series) {
      $series_title = [
        'data' => $series['title'],
        'header' => TRUE,
        'scope' => 'row',
      ];

      foreach ($series['data'] as $point) {
        if (!is_array($point)) {
          $row = [$series_title, $point, ''];
          if ($has_z_value) {
            $row[] = '';
          }
          $rows[] = $row;
          continue;
        }

        $point_values = array_values($point);
        $x_val = $point['x'] ?? $point_values[0] ?? '';
        $y_val = $point['y'] ?? $point_values[1] ?? '';

        if ($chart_type === 'heatmap') {
          if (is_numeric($x_val) && isset($categories[(int) $x_val])) {
            $x_val = $categories[(int) $x_val];
          }
          $z_val = $point['value'] ?? $point_values[2] ?? '';
        }
        else {
          $z_val = $point['r'] ?? $point['z'] ?? $point_values[2] ?? '';
        }

        $row = [$series_title, $x_val, $y_val];

        if ($has_z_value) {
          $row[] = $z_val;
        }

        $rows[] = $row;
      }
    }

    return [$header, $rows];
  }

  /**
   * Builds header and rows for standard charts.
   *
   * @param bool $single_axis
   *   If a single axis chart.
   * @param array $series_data
   *   The series data.
   * @param array $xaxis_element
   *   The x-axis element.
   * @param array $categories
   *   The categories.
   *
   * @return array
   *   The table header and rows.
   */
  protected function buildStandardTableData(bool $single_axis, array $series_data, array $xaxis_element, array $categories): array {
    $first_col_header = !empty($xaxis_element['#title']) ? $xaxis_element['#title'] : $this->t('Category');

    $header = $single_axis ? [
      ['data' => $this->t('Label'), 'scope' => 'col'],
      ['data' => $this->t('Value'), 'scope' => 'col'],
    ] : [
      ['data' => $first_col_header, 'scope' => 'col'],
    ];

    foreach ($series_data as $series) {
      if (!$single_axis) {
        $header[] = ['data' => $series['title'], 'scope' => 'col'];
      }
    }

    $rows = [];

    if ($single_axis) {
      $data_points = $series_data[0]['data'] ?? [];
      $labels = !empty($series_data[0]['labels']) ? $series_data[0]['labels'] : $categories;

      foreach ($data_points as $index => $point) {
        $row_label = $labels[$index] ?? NULL;
        $value = $point;
        if (is_array($point) && array_is_list($point) && count($point) === 2) {
          [$slice_label, $slice_value] = $point;
          if (is_scalar($slice_label) && (is_numeric($slice_value) || $slice_value === NULL)) {
            $row_label ??= $slice_label;
            $value = $slice_value;
          }
        }
        $row_label ??= $this->t('Slice @n', ['@n' => $index + 1]);
        $rows[] = [
          [
            'data' => $row_label,
            'header' => TRUE,
            'scope' => 'row',
          ],
          $this->processDataPoint($value),
        ];
      }
    }
    else {
      $max_rows = empty($series_data) ? 0 : max(array_map(fn($s) => count($s['data']), $series_data));

      for ($i = 0; $i < $max_rows; $i++) {
        $row_label = $categories[$i] ?? (string) ($i + 1);
        $row = [
          [
            'data' => $row_label,
            'header' => TRUE,
            'scope' => 'row',
          ],
        ];

        // Series data cells.
        foreach ($series_data as $series) {
          $value = $series['data'][$i] ?? '';
          $row[] = $this->processDataPoint($value);
        }
        $rows[] = $row;
      }
    }

    return [$header, $rows];
  }

  /**
   * Intelligently formats a data point based on its structure.
   *
   * @param mixed $point
   *   A data point.
   *
   * @return int|float|string|array
   *   The processed point.
   */
  private function processDataPoint(mixed $point): int|float|string|array {
    // Simple scalar (number/string).
    if (is_scalar($point)) {
      return $point;
    }

    if (!is_array($point)) {
      return '';
    }

    if (array_is_list($point)) {
      return implode(', ', array_filter($point, 'is_scalar'));
    }

    // Associative arrays: use keys as labels.
    // Example: ['high' => 10, 'low' => 5] becomes "High: 10, Low: 5".
    $parts = [];
    foreach ($point as $key => $value) {
      // Skip internal render properties (#) or temporary keys (_).
      if (str_starts_with($key, '#') || str_starts_with($key, '_')) {
        continue;
      }
      if (is_scalar($value)) {
        $parts[] = ucfirst($key) . ': ' . $value;
      }
    }

    return implode(', ', $parts);
  }

}
