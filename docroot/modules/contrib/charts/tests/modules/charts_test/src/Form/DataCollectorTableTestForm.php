<?php

namespace Drupal\charts_test\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * The data collector table test form to test the DataCollectorTable element.
 *
 * @package Drupal\charts_test\Form
 */
class DataCollectorTableTestForm extends FormBase {

  const INITIAL_ROWS = 3;
  const INITIAL_COLUMNS = 2;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'charts_data_collector_table_test_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('charts.settings');
    $form['series'] = [
      '#type' => 'chart_data_collector_table',
      '#initial_rows' => self::INITIAL_ROWS,
      '#initial_columns' => self::INITIAL_COLUMNS,
      '#table_drag' => FALSE,
      '#default_colors' => $config->get('charts_default_settings.display.colors') ?? [],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // {"name":"Number of players","color":"#0d233a","data":
    // [50,60,100,132,133,234]},{"name":"Number of coaches","color":.
    // "#ff0000","data":
    // [50,80,100,32,133,234]}.
    // A,b,c,d,e,f
  }

}
