<?php

namespace Drupal\charts_blocks\Plugin\Block;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\charts\Element\BaseSettings;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A Canvas-friendly charts block.
 *
 * Unlike the standard "charts_block", this block deliberately avoids the
 * AJAX-driven data-collector table so it renders reliably inside Drupal
 * Canvas' component form. Data is entered as CSV or JSON in a textarea and
 * stored in the block configuration ("data in the form"). Rendering still goes
 * through the Charts API "chart" render element, so it stays library-agnostic
 * and reuses the accessible-table fallback, alter hooks, and library plugins.
 *
 * Preview is intentionally omitted; Canvas renders the live component preview.
 */
#[Block(
  id: "charts_canvas_block",
  admin_label: new TranslatableMarkup("Chart (Canvas)")
)]
class ChartsCanvasBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The UUID service.
   *
   * @var \Drupal\Component\Uuid\UuidInterface
   */
  protected $uuidService;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = new static($configuration, $plugin_id, $plugin_definition);
    $instance->uuidService = $container->get('uuid');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'label' => '',
      'label_display' => '0',
      'chart_type' => 'column',
      'chart_library' => 'site_default',
      'title' => '',
      'subtitle' => '',
      'data_format' => 'csv',
      'data' => '',
      'display' => [
        'legend_position' => 'right',
        'stacking' => FALSE,
        'accessible_table' => TRUE,
      ],
      'xaxis' => ['title' => ''],
      'yaxis' => ['title' => '', 'min' => '', 'max' => ''],
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $config = $this->configuration;

    $form['chart_type'] = [
      '#type' => 'select',
      '#title' => $this->t('Chart type'),
      '#options' => $this->chartTypeOptions(),
      '#default_value' => $config['chart_type'],
      '#required' => TRUE,
    ];

    $form['chart_library'] = [
      '#type' => 'select',
      '#title' => $this->t('Charting library'),
      '#options' => ['site_default' => $this->t('Site default')] + BaseSettings::getLibraries(),
      '#default_value' => $config['chart_library'],
      '#description' => $this->t('Leave on "Site default" to use the library configured on the chart settings page.'),
    ];

    $form['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#default_value' => $config['title'],
    ];
    $form['subtitle'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Subtitle'),
      '#default_value' => $config['subtitle'],
    ];

    $form['data_format'] = [
      '#type' => 'select',
      '#title' => $this->t('Data format'),
      '#options' => [
        'csv' => $this->t('CSV'),
        'json' => $this->t('JSON'),
      ],
      '#default_value' => $config['data_format'],
    ];
    $form['data'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Data'),
      '#default_value' => $config['data'],
      '#rows' => 8,
      '#required' => TRUE,
      '#description' => $this->t('Select a data format (above), then add your data here. CSV: a header row whose first cell is the category label and remaining cells are series names, followed by one row per category. Example:<br><code>Quarter,Product A,Product B<br>Q1,120,90<br>Q2,145,110</code><br><br>JSON: <code>{"categories":["Q1","Q2"],"series":[{"name":"Product A","data":[120,145],"color":"#1f77b4"},{"name":"Product B","data":[90,110],"target_axis":"secondary_yaxis","chart_type":"line"}]}</code><br><br>For pie and donut charts only the first series is used; categories become the slice labels.'),
    ];

    $form['display'] = [
      '#type' => 'details',
      '#title' => $this->t('Display'),
      '#tree' => TRUE,
    ];
    $form['display']['legend_position'] = [
      '#type' => 'select',
      '#title' => $this->t('Legend position'),
      '#options' => [
        '' => $this->t('None'),
        'top' => $this->t('Top'),
        'right' => $this->t('Right'),
        'bottom' => $this->t('Bottom'),
        'left' => $this->t('Left'),
      ],
      '#default_value' => $config['display']['legend_position'],
    ];
    $form['display']['stacking'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable stacking'),
      '#default_value' => !empty($config['display']['stacking']),
    ];
    $form['display']['accessible_table'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Include an accessible data table'),
      '#description' => $this->t('Adds a collapsible, screen-reader-friendly table of the chart data.'),
      '#default_value' => !empty($config['display']['accessible_table']),
    ];

    $form['xaxis'] = [
      '#type' => 'details',
      '#title' => $this->t('Horizontal axis'),
      '#tree' => TRUE,
    ];
    $form['xaxis']['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#default_value' => $config['xaxis']['title'],
    ];

    $form['yaxis'] = [
      '#type' => 'details',
      '#title' => $this->t('Vertical axis'),
      '#tree' => TRUE,
    ];
    $form['yaxis']['title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Title'),
      '#default_value' => $config['yaxis']['title'],
    ];
    $form['yaxis']['min'] = [
      '#type' => 'number',
      '#title' => $this->t('Minimum'),
      '#default_value' => $config['yaxis']['min'],
    ];
    $form['yaxis']['max'] = [
      '#type' => 'number',
      '#title' => $this->t('Maximum'),
      '#default_value' => $config['yaxis']['max'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockValidate($form, FormStateInterface $form_state) {
    $format = $form_state->getValue('data_format');
    $data = (string) $form_state->getValue('data');

    if (trim($data) === '') {
      return;
    }

    if ($format === 'json') {
      try {
        $decoded = Json::decode($data);
        if (json_last_error() !== JSON_ERROR_NONE) {
          $form_state->setErrorByName('data', $this->t('Invalid JSON syntax.'));
        }
        elseif (!is_array($decoded) || empty($decoded['series'])) {
          $form_state->setErrorByName('data', $this->t('Enter valid JSON containing a non-empty "series" array.'));
        }
      }
      catch (\Exception $e) {
        $form_state->setErrorByName('data', $this->t('Invalid JSON syntax.'));
      }
    }
    else {
      $parsed = $this->parseCsv($data);
      if (empty($parsed['series']) || empty($parsed['categories'])) {
        $form_state->setErrorByName('data', $this->t('Enter CSV with a header row and at least one data row.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $this->configuration['chart_type'] = $form_state->getValue('chart_type');
    $this->configuration['chart_library'] = $form_state->getValue('chart_library');
    $this->configuration['title'] = $form_state->getValue('title');
    $this->configuration['subtitle'] = $form_state->getValue('subtitle');
    $this->configuration['data_format'] = $form_state->getValue('data_format');
    $data = $form_state->getValue('data');
    if (is_array($data) || is_object($data)) {
      $data = Json::encode($data);
    }
    $this->configuration['data'] = $data;
    $this->configuration['display'] = [
      'legend_position' => $form_state->getValue(['display', 'legend_position']),
      'stacking' => (bool) $form_state->getValue(['display', 'stacking']),
      'accessible_table' => (bool) $form_state->getValue(['display', 'accessible_table']),
    ];
    $this->configuration['xaxis'] = [
      'title' => $form_state->getValue(['xaxis', 'title']),
    ];
    $this->configuration['yaxis'] = [
      'title' => $form_state->getValue(['yaxis', 'title']),
      'min' => $form_state->getValue(['yaxis', 'min']),
      'max' => $form_state->getValue(['yaxis', 'max']),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $config = $this->configuration;
    $type = $config['chart_type'] ?: 'column';
    $parsed = $this->parseData($config['data_format'] ?? 'csv', $config['data'] ?? '');

    if (empty($parsed['series'])) {
      return [
        '#markup' => $this->t('No chart data provided.'),
        '#cache' => ['max-age' => 0],
      ];
    }

    $categories = $parsed['categories'];
    $series = array_values($parsed['series']);
    $single_axis = in_array($type, ['pie', 'donut'], TRUE);

    $chart_id = 'charts_canvas__' . ($config['id'] ?? $this->getPluginId());
    $element = [
      '#type' => 'chart',
      '#id' => 'chart-' . $this->uuidService->generate(),
      '#chart_id' => $chart_id,
      '#chart_type' => $type,
      '#title' => $config['title'] ?? '',
      '#subtitle' => $config['subtitle'] ?? '',
    ];

    $library = $config['chart_library'] ?? '';
    if ($library && $library !== 'site_default') {
      $element['#chart_library'] = $library;
    }

    $legend = $config['display']['legend_position'] ?? '';
    $element['#legend'] = (bool) $legend;
    if ($legend) {
      $element['#legend_position'] = $legend;
    }
    if (!empty($config['display']['stacking'])) {
      $element['#stacking'] = TRUE;
    }
    $element['#accessible_table'] = !empty($config['display']['accessible_table']) ? 'collapsible' : 'disabled';

    // X axis.
    $element['xaxis'] = [
      '#type' => 'chart_xaxis',
      '#labels' => $categories,
    ];
    if (!empty($config['xaxis']['title'])) {
      $element['xaxis']['#title'] = $config['xaxis']['title'];
    }

    // Y axis / series.
    if ($single_axis) {
      $first = reset($series);
      $element['series_0'] = [
        '#type' => 'chart_data',
        '#title' => $first['name'] ?? ($config['title'] ?? ''),
        '#data' => $this->toNumbers($first['data']),
      ];
      if (!empty($first['color'])) {
        $element['series_0']['#color'] = $first['color'];
      }
    }
    else {
      $element['yaxis'] = ['#type' => 'chart_yaxis'];
      if (!empty($config['yaxis']['title'])) {
        $element['yaxis']['#title'] = $config['yaxis']['title'];
      }
      if (($config['yaxis']['min'] ?? '') !== '') {
        $element['yaxis']['#min'] = (int) $config['yaxis']['min'];
      }
      if (($config['yaxis']['max'] ?? '') !== '') {
        $element['yaxis']['#max'] = (int) $config['yaxis']['max'];
      }

      $has_secondary = FALSE;
      foreach ($series as $s) {
        if (($s['target_axis'] ?? '') === 'secondary_yaxis') {
          $has_secondary = TRUE;
          break;
        }
      }
      if ($has_secondary) {
        $element['secondary_yaxis'] = [
          '#type' => 'chart_yaxis',
          '#opposite' => TRUE,
        ];
      }

      foreach ($series as $i => $s) {
        $key = 'series_' . $i;
        $element[$key] = [
          '#type' => 'chart_data',
          '#title' => $s['name'] ?? '',
          '#data' => $this->toNumbers($s['data']),
        ];
        if (!empty($s['color'])) {
          $element[$key]['#color'] = $s['color'];
        }
        if (!empty($s['chart_type'])) {
          $element[$key]['#chart_type'] = $s['chart_type'];
        }
        if (isset($element['secondary_yaxis']) && ($s['target_axis'] ?? '') === 'secondary_yaxis') {
          $element[$key]['#target_axis'] = 'secondary_yaxis';
        }
      }
    }

    return $element;
  }

  /**
   * Returns the supported chart types for this simplified data model.
   *
   * @return array
   *   An array of type id => label.
   */
  protected function chartTypeOptions(): array {
    return [
      'line' => $this->t('Line'),
      'area' => $this->t('Area'),
      'bar' => $this->t('Bar'),
      'column' => $this->t('Column'),
      'pie' => $this->t('Pie'),
      'donut' => $this->t('Donut'),
    ];
  }

  /**
   * Parses the raw data field into categories and series.
   *
   * @param string $format
   *   Either 'csv' or 'json'.
   * @param string $raw
   *   The raw data string.
   *
   * @return array
   *   An array with 'categories' and 'series' keys.
   */
  protected function parseData(string $format, string $raw): array {
    return $format === 'json' ? $this->parseJson($raw) : $this->parseCsv($raw);
  }

  /**
   * Parses CSV text into categories and series.
   *
   * @param string $raw
   *   The CSV text.
   *
   * @return array
   *   An array with 'categories' and 'series' keys.
   */
  protected function parseCsv(string $raw): array {
    $empty = ['categories' => [], 'series' => []];
    $lines = preg_split('/\r\n|\r|\n/', trim($raw)) ?: [];
    $rows = [];
    foreach ($lines as $line) {
      if (trim($line) === '') {
        continue;
      }
      $rows[] = str_getcsv($line);
    }
    if (count($rows) < 2) {
      return $empty;
    }

    $header = array_shift($rows);
    $series_names = array_slice($header, 1);
    if (!$series_names) {
      return $empty;
    }

    $series = [];
    foreach ($series_names as $name) {
      $series[] = ['name' => trim((string) $name), 'data' => []];
    }

    $categories = [];
    foreach ($rows as $row) {
      $categories[] = trim((string) ($row[0] ?? ''));
      foreach ($series_names as $idx => $name) {
        $series[$idx]['data'][] = $row[$idx + 1] ?? NULL;
      }
    }

    return [
      'categories' => $categories,
      'series' => $series,
    ];
  }

  /**
   * Parses JSON text into categories and series.
   *
   * @param string $raw
   *   The JSON text.
   *
   * @return array
   *   An array with 'categories' and 'series' keys.
   */
  protected function parseJson(string $raw): array {
    try {
      $decoded = Json::decode($raw);

      // If parsing failed (syntax error, depth limit reached), return
      // empty arrays.
      if (json_last_error() !== JSON_ERROR_NONE) {
        return [
          'categories' => [],
          'series' => [],
        ];
      }
    }
    catch (\Exception $e) {
      return [
        'categories' => [],
        'series' => [],
      ];
    }

    if (!is_array($decoded) || empty($decoded['series'])) {
      return [
        'categories' => [],
        'series' => [],
      ];
    }

    $series = [];
    foreach ($decoded['series'] as $s) {
      if (!is_array($s)) {
        continue;
      }
      $series[] = [
        'name' => $s['name'] ?? '',
        'data' => $s['data'] ?? [],
        'color' => $s['color'] ?? NULL,
        'target_axis' => $s['target_axis'] ?? NULL,
        'chart_type' => $s['chart_type'] ?? NULL,
      ];
    }

    return [
      'categories' => $decoded['categories'] ?? [],
      'series' => $series,
    ];
  }

  /**
   * Casts a list of cell values to numbers, preserving gaps as NULL.
   *
   * @param array $values
   *   The raw values.
   *
   * @return array
   *   The numeric values.
   */
  protected function toNumbers(array $values): array {
    return array_map(function ($value) {
      if (is_array($value)) {
        return $this->toNumbers($value);
      }
      if ($value === NULL || $value === '') {
        return NULL;
      }
      return is_numeric($value) ? $value + 0 : $value;
    }, $values);
  }

}
