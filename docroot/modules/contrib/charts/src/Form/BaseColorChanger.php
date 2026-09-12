<?php

namespace Drupal\charts\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a generic form for dynamically changing chart colors.
 *
 * This form is utilized by various charting libraries (Highcharts, Chart.js,
 * Google Charts, etc.) to provide a unified color-picker interface.
 */
class BaseColorChanger extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'charts_base_color_changer';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $chart_id = $form_state->get('chart_id');
    $series = $form_state->get('chart_series');
    $chart_type = $form_state->get('chart_type');

    if (!$series || !$chart_id) {
      return $form;
    }

    $form['#attributes']['class'][] = 'charts-color-changer';
    $form['color_changer_wrapper'] = [
      '#title' => $this->t('Change the colors'),
      '#type' => 'fieldset',
    ];

    foreach ($series as $index => $item) {
      // Logic for pie/donut type charts.
      if (in_array($chart_type, ['pie', 'donut', 'doughnut'])) {
        $data_items = $item['data'] ?? [];
        foreach ($data_items as $datum_index => $datum_value) {
          $form['color_changer_wrapper']['color_' . $datum_index] = $this->buildColorField(
            $datum_value['name'] ?? "Item $datum_index",
            $datum_value['color'] ?? '#000',
            $chart_id,
            $chart_type,
            $datum_index
          );
        }
      }
      else {
        // Standard series-based coloring.
        $form['color_changer_wrapper']['color_' . $index] = $this->buildColorField(
          $item['name'] ?? "Series $index",
          $item['color'] ?? '#000',
          $chart_id,
          $chart_type,
          $index
        );
      }
    }
    return $form;
  }

  /**
   * Builds a color picker textfield for a specific chart series or data point.
   *
   * @param string $label
   *   The label for the color field.
   * @param string $default
   *   The default hex color value.
   * @param string $chart_id
   *   The unique ID of the chart to be updated.
   * @param string $chart_type
   *   The library-specific chart type.
   * @param int|string $index
   *   The index of the series or data point.
   *
   * @return array
   *   A render array for a color input field.
   */
  protected function buildColorField(string $label, string $default, string $chart_id, string $chart_type, $index): array {
    return [
      '#type' => 'textfield',
      '#title' => $this->t('@label Color', ['@label' => $label]),
      '#attributes' => [
        'TYPE' => 'color',
        'autocomplete' => 'off',
        'data-charts-color-info' => json_encode([
          'series_index' => $index,
          'series_name' => $label,
          'chart_id' => $chart_id,
          'chart_type' => $chart_type,
        ]),
      ],
      '#default_value' => $default,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // This form is handled via client-side JavaScript behaviors.
  }

}
