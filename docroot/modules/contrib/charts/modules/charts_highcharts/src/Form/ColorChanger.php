<?php

namespace Drupal\charts_highcharts\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Implements a color-changer form.
 */
class ColorChanger extends FormBase {

  /**
   * {@inheritDoc}
   */
  public function getFormId() {
    return 'charts_highcharts_color_changer';
  }

  /**
   * {@inheritDoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $chart_id = $form_state->get('chart_id');
    $series = $form_state->get('chart_series');
    $chart_type = $form_state->get('chart_type');
    $y_axis = $form_state->get('y_axis');
    if (!$series || !$chart_id) {
      return $form;
    }
    $form['#attributes']['class'][] = 'charts-color-changer';
    $form['color_changer_wrapper'] = [
      '#title' => $this->t('Change the colors'),
      '#type' => 'fieldset',
      '#collapsible' => TRUE,
      '#collapsed' => FALSE,
    ];
    foreach ($series as $index => $item) {
      if ($chart_type === 'pie') {
        foreach ($item['data'] as $datum_index => $datum_value) {
          $form['color_changer_wrapper']['color_' . $datum_index] = [
            '#type' => 'textfield',
            '#title' => $this->t('@label Color', ['@label' => $datum_value['name'] ?? '']),
            '#attributes' => [
              'TYPE' => 'color',
              'style' => 'min-width:50px;',
              'data-charts-highcharts-color-info' => json_encode([
                'series_index' => $datum_index,
                'chart_id' => $chart_id,
                'chart_type' => $chart_type,
              ]),
            ],
            '#size' => 10,
            '#maxlength' => 7,
            '#default_value' => $datum_value['color'] ?? '#000',
          ];
        }
      }
      elseif ($chart_type === 'gauge') {
        $gauge_colors = [
          'red' => '#ff0000',
          'yellow' => '#ffff00',
          'green' => '#008000',
        ];
        foreach ($y_axis['plotBands'] as $datum_index => $datum_value) {
          $form['color_changer_wrapper']['color_' . $datum_index] = [
            '#type' => 'textfield',
            '#title' => $this->t('@label Color', ['@label' => $item['name'] . ' (' . $datum_value['color'] . ')']),
            '#attributes' => [
              'TYPE' => 'color',
              'style' => 'min-width:50px;',
              'data-charts-highcharts-color-info' => json_encode([
                'series_index' => $datum_index,
                'chart_id' => $chart_id,
                'chart_type' => $chart_type,
              ]),
            ],
            '#size' => 10,
            '#maxlength' => 7,
            '#default_value' => $gauge_colors[$datum_value['color']] ?? '#000',
          ];
        }
      }
      else {
        $form['color_changer_wrapper']['color_' . $index] = [
          '#type' => 'textfield',
          '#title' => $this->t('@label Color', ['@label' => $item['name']]),
          '#attributes' => [
            'TYPE' => 'color',
            'style' => 'min-width:50px;',
            'data-charts-highcharts-color-info' => json_encode([
              'series_index' => $index,
              'chart_id' => $chart_id,
            ]),
          ],
          '#size' => 10,
          '#maxlength' => 7,
          '#default_value' => $item['color'] ?? '#000',
        ];
      }

    }

    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {}

}
