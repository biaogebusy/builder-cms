<?php

namespace Drupal\charts\Element;

use Drupal\charts\ColorHelperTrait;
use Drupal\charts\Plugin\chart\Library\ChartInterface;
use Drupal\Component\Utility\Environment;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Component\Utility\Unicode;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Element\FormElement;

/**
 * Provides a chart data collector table form element.
 *
 * @FormElement("chart_data_collector_table")
 */
class ChartDataCollectorTable extends FormElement {

  use ColorHelperTrait;
  use ElementFormStateTrait;

  const FIRST_COLUMN = 'first_column';
  const FIRST_ROW = 'first_row';

  /**
   * {@inheritdoc}
   */
  public function getInfo() {
    $class = get_class($this);
    return [
      '#input' => TRUE,
      // Either to enable csv import.
      '#import_csv' => TRUE,
      '#import_csv_separator' => ',',
      // The initial number of rows to generate.
      '#initial_rows' => 5,
      // The initial number of columns to generate.
      '#initial_columns' => 2,
      // The optional element the table should be wrapped in.
      '#table_wrapper' => '',
      '#table_wrapper_attributes' => [],
      '#table_attributes' => [],
      // Allows to toggle on/off drupal tabledrag functionality.
      '#table_drag' => TRUE,
      '#default_colors' => [],
      '#process' => [
        [$class, 'processDataCollectorTable'],
      ],
      '#element_validate' => [
        [$class, 'validateDataCollectorTable'],
      ],
      '#theme_wrappers' => ['container'],
    ];
  }

  /**
   * Processes the element to render a table to collect a data for the chart.
   *
   * @param array $element
   *   The element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param array $complete_form
   *   The complete form.
   *
   * @return array
   *   The processed element.
   */
  public static function processDataCollectorTable(array &$element, FormStateInterface $form_state, array &$complete_form) {
    $parents = $element['#parents'];
    $id_prefix = implode('-', $parents);
    $wrapper_id = Html::getUniqueId($id_prefix . '-ajax-wrapper');
    $value = $element['#value'];
    $required = !empty($element['#required']);
    $user_input = $form_state->getUserInput();

    $element_state = self::getElementState($parents, $form_state);
    // Getting columns and rows count.
    if (empty($element_state['data_collector_table']) || empty($element_state['table_categories_identifier'])) {
      $identifier_value = $value['table_categories_identifier'] ?? self::FIRST_COLUMN;
      $element_state['table_categories_identifier'] = $identifier_value;
      $element_state['data_collector_table'] = $value['data_collector_table'] ?? [];
      $element_state['data_collector_table'] = $element_state['data_collector_table'] ?: self::initializeEmptyTable($element, $identifier_value);
      self::setElementState($parents, $form_state, $element_state);
    }
    else {
      // This is hack to make ajax call return the proper identifier.
      $element_state['table_categories_identifier'] = $value['table_categories_identifier'];
    }

    // Enforce tree.
    $element = [
      '#tree' => TRUE,
      '#prefix' => '<div id="' . $wrapper_id . '">',
      '#suffix' => '</div>',
      // Pass the id along to other methods.
      '#wrapper_id' => $wrapper_id,
    ] + $element;

    $element['table_categories_identifier'] = [
      '#type' => 'radios',
      '#title' => t('Categories are identified by'),
      '#options' => [
        self::FIRST_COLUMN => t('First column'),
        self::FIRST_ROW => t('First row'),
      ],
      '#description' => t('Select whether the first row or column hold the categories data'),
      '#required' => $required,
      '#default_value' => $element_state['table_categories_identifier'],
      '#ajax' => [
        'callback' => [get_called_class(), 'ajaxRefresh'],
        'progress' => ['type' => 'throbber'],
        'wrapper' => $wrapper_id,
        'effect' => 'fade',
      ],
    ];

    $table = [
      '#type' => 'table',
      '#tree' => TRUE,
      '#header' => [],
      '#responsive' => FALSE,
      '#attributes' => [
        'class' => ['data-collector-table'],
      ],
    ];

    $table_drag = $element['#table_drag'];
    $table_drag_group = Html::cleanCssIdentifier($id_prefix . '-order-weight');
    if ($table_drag) {
      $table['#tabledrag'] = [
        [
          'action' => 'order',
          'relationship' => 'sibling',
          'group' => $table_drag_group,
        ],
      ];
    }

    if ($element['#table_wrapper'] === 'container') {
      $element['table_wrapper'] = [
        '#type' => 'container',
        '#attributes' => $element['#table_wrapper_attributes'],
        '#tree' => FALSE,
      ];
      $element['table_wrapper']['data_collector_table'] = &$table;
    }
    else {
      $element['data_collector_table'] = &$table;
    }

    $rows = count($element_state['data_collector_table']);

    // Make the weight list always reflect the current number of values.
    $max_weight = count($element_state['data_collector_table']);
    $max_row = max(array_keys($element_state['data_collector_table']));

    // The first column need to be for colors.
    $is_first_column = $element_state['table_categories_identifier'] === self::FIRST_COLUMN;
    $first_row_key = NULL;
    foreach ($element_state['data_collector_table'] as $i => $row) {
      $first_row_key = $first_row_key ?? $i;
      $table_first_row = $i === $first_row_key;
      $add_color_first_row = ($is_first_column && $table_first_row);
      $first_col_key = NULL;

      $row_form = &$table[$i];
      $row_form['#attributes']['class'][] = 'data-collector-table--row';

      // Adding the row textfield cells.
      foreach ($row as $j => $column) {
        if ($j === 'weight') {
          continue;
        }

        $first_col_key = $first_col_key ?? $j;
        $table_first_col = $j === $first_col_key;
        // To be used to skip color input on cell[0][0].
        $is_category_cell = $table_first_col && $table_first_row;
        $row_form[$j]['data'] = [
          '#type' => 'textfield',
          '#title' => t('Data for column @col - Row @row', [
            '@row' => $i,
            '@col' => $j,
          ]),
          '#title_display' => 'invisible',
          '#size' => 10,
          '#default_value' => is_array($column) ? $column['data'] : $column,
          '#wrapper_attributes' => [
            'class' => ['data-collector-table--row--cell'],
          ],
        ];

        if (!$is_category_cell && ($add_color_first_row || (!$is_first_column && $j === $first_col_key))) {
          if (empty($column['color'])) {
            $color_index = $is_first_column ? $j : $i;
            $column['color'] = $element['#default_colors'][$color_index - 1] ?? self::randomColor();
          }
          $row_form[$j]['#wrapper_attributes'] = [
            'class' => ['container-inline'],
          ];
          $row_form[$j]['color'] = [
            '#type' => 'textfield',
            '#title' => t('Color'),
            '#title_display' => 'invisible',
            '#attributes' => [
              'TYPE' => 'color',
              'style' => 'min-width:50px;',
            ],
            '#size' => 10,
            '#maxlength' => 7,
            '#default_value' => $column['color'],
          ];
        }
      }

      // Adding weight if table drag enabled.
      if ($table_drag) {
        $row_form['#attributes']['class'][] = 'draggable';
        if (($i + 1) === $rows) {
          $default_weight = $max_weight;
        }
        else {
          $default_weight = $max_row + 1;
        }
        $row_form['weight'] = [
          '#type' => 'weight',
          '#title' => t('Weight'),
          '#title_display' => 'invisible',
          '#delta' => $max_weight,
          '#default_value' => $element_state['data_collector_table'][$i]['weight'] ?? $default_weight,
          '#attributes' => [
            'class' => [$table_drag_group],
          ],
        ];
        // Used by SortArray::sortByWeightProperty to sort the rows.
        if (isset($user_input['data_collector_table'][$i])) {
          $input_weight = $user_input['data_collector_table'][$i]['weight'];
          // Make sure the weight is not out of bounds due to removals.
          if ($user_input['data_collector_table'][$i]['weight'] > $max_weight) {
            $input_weight = $max_weight;
          }
          // Reflect the updated user input on the element.
          $row_form['weight']['#value'] = $input_weight;
          $row_form['#weight'] = $input_weight;
        }
        else {
          $row_form['#weight'] = $default_weight;
        }
      }

      // Row delete button.
      $row_form['delete'] = self::buildOperationButton('delete', 'row', $id_prefix, $wrapper_id, $i, [], [
        'class' => ['data-collector-table--row--delete'],
      ]);
    }

    $colspan = 1;
    if ($table_drag) {
      // Sort the values by weight. Ensures weight is preserved on ajax refresh.
      uasort($table, [
        '\Drupal\Component\Utility\SortArray',
        'sortByWeightProperty',
      ]);

      // Increasing colspan when weight column is added.
      $colspan = 2;
    }

    // Building the column delete button.
    $table['_delete_column_buttons'] = [
      '#attributes' => ['class' => ['data-collector-table--column-deletes-row']],
    ];
    // Using first row to get the count of columns.
    $first_row = current($element_state['data_collector_table']);
    // Using array filter to exclude weight key when grabbing the row columns.
    $columns = self::excludeWeightColumnFromRow($first_row);
    $max_column = max(array_keys($first_row));
    foreach ($columns as $column) {
      $table['_delete_column_buttons'][$column] = self::buildOperationButton('delete', 'column', $id_prefix, $wrapper_id, $column, [], [
        'class' => ['data-collector-table--column--delete'],
      ]);
      if ($column === $max_column) {
        $table['_delete_column_buttons'][$column]['#wrapper_attributes']['colspan'] = $colspan;
      }
    }
    // Empty Column under delete operation placeholder.
    $table['_delete_column_buttons'][$max_column + 1] = [
      '#markup' => '',
    ];

    // Footer operations.
    $table['_operations'] = [
      '#attributes' => ['class' => ['data-collector-table--operations-row']],
    ];
    $table['_operations']['wrapper'] = [
      '#type' => 'container',
      '#wrapper_attributes' => [
        'colspan' => count($columns) + $colspan,
      ],
    ];
    $table['_operations']['wrapper']['add_column'] = self::buildOperationButton('add', 'column', $id_prefix, $wrapper_id, NULL);
    $table['_operations']['wrapper']['add_row'] = self::buildOperationButton('add', 'row', $id_prefix, $wrapper_id, NULL);

    if ($element['#import_csv']) {
      $element['import'] = [
        '#type' => 'details',
        '#title' => t('Import Data from CSV'),
        '#description' => t('Note importing data from CSV will overwrite all the current data entry in the table.'),
        '#open' => FALSE,
      ];
      $element['import']['csv'] = [
        '#name' => 'files[' . $id_prefix . ']',
        '#title' => t('File upload'),
        '#title_display' => 'invisible',
        '#type' => 'file',
        '#upload_validators' => [
          'file_validate_extensions' => ['csv'],
          'file_validate_size' => [Environment::getUploadMaxSize()],
        ],
      ];
      $element['import']['upload'] = [
        '#type' => 'submit',
        '#value' => t('Upload CSV'),
        '#name' => $id_prefix . '-import-csv',
        '#attributes' => [
          'class' => [Html::cleanCssIdentifier($id_prefix . '--import-csv')],
        ],
        '#submit' => [[get_called_class(), 'importCsvToTableSubmit']],
        '#limit_validation_errors' => [
          array_merge($parents, ['import', 'csv']),
          array_merge($parents, ['import', 'upload']),
        ],
        '#ajax' => [
          'callback' => [get_called_class(), 'ajaxRefresh'],
          'progress' => ['type' => 'throbber'],
          'wrapper' => $wrapper_id,
          'effect' => 'fade',
        ],
        '#operation' => 'csv',
        '#csv_separator' => $element['#import_csv_separator'] ?? ',',
      ];
    }
    $element['#attributes']['style'] = 'overflow: auto;';

    return $element;
  }

  /**
   * Validates the data collected.
   *
   * @param array $element
   *   The form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   */
  public static function validateDataCollectorTable(array $element, FormStateInterface $form_state) {
    $parents = $element['#parents'];
    $value = $form_state->getValue($parents);

    // Remove empty rows and unneeded keys.
    foreach ($value['data_collector_table'] as $row_key => $row) {
      if (!is_numeric($row_key)) {
        unset($value['data_collector_table'][$row_key]);
        continue;
      }
      foreach ($row as $column_key => $column) {
        if (!is_numeric($column_key)) {
          unset($value['data_collector_table'][$row_key][$column_key]);
        }
      }
    }
    unset($value['import']);
    $form_state->setValue($parents, $value);

    if ($element['#required'] && empty($value['table_categories_identifier'])) {
      $form_state->setError($element['table_categories_identifier'], t('Please select how categories should be identified.'));
    }
  }

  /**
   * Ajax callback.
   */
  public static function ajaxRefresh(array $form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $operation = $triggering_element['#operation'] ?? '';

    if ($operation === 'csv' || (!$operation && $triggering_element['#type'] === 'radio')) {
      $length = -2;
    }
    else {
      $length = $operation === 'add' ? -4 : -3;
    }
    $element_parents = array_slice($triggering_element['#array_parents'], 0, $length);
    return NestedArray::getValue($form, $element_parents);
  }

  /**
   * Submit callback for table add and delete operations.
   */
  public static function tableOperationSubmit(array $form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $operation_on = $triggering_element['#operation_on'];
    $operation = $triggering_element['#operation'];
    $length = $operation === 'add' ? -4 : -3;
    $element_parents = array_slice($triggering_element['#parents'], 0, $length);
    if (!$element_parents) {
      $length = $operation == 'add' ? -4 : -3;
      $element_parents = array_slice($triggering_element['#array_parents'], 0, $length);
    }
    $element_state = self::getElementState($element_parents, $form_state);
    $index = $triggering_element['#' . $operation_on . '_index'] ?? NULL;

    if ($operation_on === 'row') {
      $element_state = self::tableRowOperation($element_state, $form_state, $operation, $index);
    }
    else {
      $element_state = self::tableColumnOperation($element_state, $form_state, $operation, $element_parents, $index);
    }

    self::setElementState($element_parents, $form_state, $element_state);
    $form_state->setRebuild();
  }

  /**
   * Submit callback for table csv import operations.
   */
  public static function importCsvToTableSubmit(array $form, FormStateInterface $form_state) {
    $triggering_element = $form_state->getTriggeringElement();
    $element_parents = array_slice($triggering_element['#parents'], 0, -2);
    $id_prefix = implode('-', $element_parents);
    $files = \Drupal::request()->files->get('files');
    /** @var  \Symfony\Component\HttpFoundation\File\UploadedFile $file_upload */
    $file_upload = $files[$id_prefix];

    $handle = $file_upload ? fopen($file_upload->getPathname(), 'r') : NULL;
    if ($handle) {
      // Checking the encoding of the CSV file to be UTF-8.
      $encoding = 'UTF-8';
      if (function_exists('mb_detect_encoding')) {
        $file_contents = file_get_contents($file_upload->getPathname());
        $encodings = ['UTF-8', 'ISO-8859-1', 'WINDOWS-1251'];
        $encodings_list = implode(',', $encodings);
        $encoding = mb_detect_encoding($file_contents, $encodings_list);
      }

      // Populate CSV values.
      $rows_count = 0;
      $element_state = [];
      $separator = $triggering_element['#csv_separator'];
      while ($row = fgetcsv($handle, 0, $separator)) {
        foreach ($row as $column_value) {
          $element_state['data_collector_table'][$rows_count][] = [
            'data' => self::convertEncoding($column_value, $encoding),
          ];
        }
        $rows_count++;
      }
      fclose($handle);

      \Drupal::messenger()->addMessage(t('Successfully imported @file', [
        '@file' => $file_upload->getClientOriginalName(),
      ]));

      // Updating form state storage.
      self::setElementState($element_parents, $form_state, $element_state);
      // Making sure that the user input is updated as well.
      $input = $form_state->getUserInput();
      NestedArray::setValue($input, $element_parents, $element_state);
      $form_state->setUserInput($input);
    }
    else {
      \Drupal::messenger()
        ->addError(t('There was a problem importing the provided file data.'));
    }

    $form_state->setRebuild();
  }

  /**
   * Utility method to build a button render array for the various data table.
   *
   * Operation.
   */
  private static function buildOperationButton($operation, $on, $id_prefix, $wrapper_id, $index = NULL, $attributes = [], $wrapper_attributes = []) {
    $name = $id_prefix . '_' . $operation . '_' . $on;
    $submit = [];

    if (!is_null($index)) {
      $name .= '_' . $index;
      $submit['#' . $on . '_index'] = $index;
    }

    if ($attributes) {
      $submit['#attributes'] = $attributes;
    }

    if ($wrapper_attributes) {
      $submit['#wrapper_attributes'] = $wrapper_attributes;
    }

    $value = [];
    $value['add']['row'] = t('Add row');
    $value['add']['column'] = t('Add column');
    $value['delete']['row'] = t('Delete row');
    $value['delete']['column'] = t('Delete column');

    $submit += [
      '#type' => 'submit',
      '#name' => $name,
      '#value' => $value[$operation][$on],
      '#limit_validation_errors' => [],
      '#submit' => [[get_called_class(), 'tableOperationSubmit']],
      '#operation' => $operation,
      '#operation_on' => $on,
      '#ajax' => [
        'callback' => [get_called_class(), 'ajaxRefresh'],
        'wrapper' => $wrapper_id,
        'effect' => 'fade',
      ],
    ];

    return $submit;
  }

  /**
   * Initializes an empty table.
   *
   * @param array $element
   *   The element.
   * @param string $identifier_value
   *   The identifier value.
   *
   * @return array
   *   The element state storage.
   */
  private static function initializeEmptyTable(array $element, string $identifier_value) {
    $is_first_column = $identifier_value === self::FIRST_COLUMN;
    $columns = $element['#initial_columns'];
    $columns_arr = range(0, $columns - 1);
    $rows = $element['#initial_rows'];
    $rows_arr = range(0, $rows - 1);

    $data = [];
    $first_row_key = NULL;
    $counter_default_used_color_index = 0;
    $max_default_colors = count($element['#default_colors']);
    foreach ($rows_arr as $i) {
      $first_row_key = $first_row_key ?? $i;
      $table_first_row = $i === $first_row_key;
      $first_col_key = NULL;
      foreach ($columns_arr as $j) {
        $first_col_key = $first_col_key ?? $j;
        $table_first_col = $j === $first_col_key;
        // Used to skip category cell.
        $is_category_cell = $table_first_col && $table_first_row;
        $data[$i][$j]['data'] = '';
        if (!$is_category_cell && (($is_first_column && $i === $first_row_key) || (!$is_first_column && $j === $first_col_key))) {
          if ($counter_default_used_color_index === $max_default_colors) {
            $counter_default_used_color_index = 0;
          }
          $data[$i][$j]['color'] = $element['#default_colors'][$counter_default_used_color_index] ?? self::randomColor();
          $counter_default_used_color_index++;
        }
      }
    }
    return $data;
  }

  /**
   * Performs add or delete operation on the table row.
   *
   * @param array $element_state
   *   The element state storage.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $op
   *   The operation.
   * @param null|int $index
   *   The row index.
   *
   * @return array
   *   The updated element state storage.
   */
  private static function tableRowOperation(array $element_state, FormStateInterface $form_state, $op, $index = NULL) {
    if ($op === 'delete') {
      // When only one row left we just empty it's columns.
      if (count($element_state['data_collector_table']) === 1) {
        $row = $element_state['data_collector_table'][$index];
        $element_state['data_collector_table'][$index][] = self::emptyRowColumns($row);
        return $element_state;
      }
      unset($element_state['data_collector_table'][$index]);
    }
    else {
      $first_row = current($element_state['data_collector_table']);
      $element_state['data_collector_table'][] = self::emptyRowColumns($first_row);
    }

    return $element_state;
  }

  /**
   * Performs add or delete operation on the table column.
   *
   * @param array $element_state
   *   The element state storage.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   * @param string $op
   *   The operation.
   * @param array $element_parents
   *   The element parents.
   * @param null|int $index
   *   The column index.
   *
   * @return array
   *   The updated element state storage.
   */
  private static function tableColumnOperation(array $element_state, FormStateInterface $form_state, $op, array $element_parents, $index = NULL) {
    if ($op === 'delete') {
      foreach ($element_state['data_collector_table'] as $row_key => $columns) {
        $row = $element_state['data_collector_table'][$row_key];
        if (count(self::excludeWeightColumnFromRow($row)) === 1) {
          $element_state['data_collector_table'][$row_key][$index]['data'] = '';
        }
        else {
          array_splice($element_state['data_collector_table'][$row_key], $index, 1);

          // Making sure that the user input is updated as well.
          $user_input = $form_state->getUserInput();
          $values = NestedArray::getValue($form_state->getUserInput(), $element_parents);
          if (!empty($values['data_collector_table'][$row_key][$index])) {
            array_splice($values['data_collector_table'][$row_key], $index, 1);
          }
          NestedArray::setValue($user_input, $element_parents, $values);
          $form_state->setUserInput($user_input);
        }
      }
    }
    else {
      foreach ($element_state['data_collector_table'] as $row_key => $columns) {
        $element_state['data_collector_table'][$row_key][]['data'] = '';
      }
    }

    return $element_state;
  }

  /**
   * Excludes weight column from row.
   *
   * @param array $row
   *   The row.
   *
   * @return array
   *   The columns.
   */
  private static function excludeWeightColumnFromRow(array $row) {
    return array_filter(array_keys($row), function ($key) {
      return is_int($key);
    });
  }

  /**
   * Empty row columns.
   *
   * @param array $row
   *   The row.
   *
   * @return array
   *   The empty row column.
   */
  private static function emptyRowColumns(array $row) {
    $columns = self::excludeWeightColumnFromRow($row);
    $empty_row_columns = [];
    foreach ($columns as $key => $column) {
      $empty_row_columns[$key]['data'] = '';
    }
    return $empty_row_columns;
  }

  /**
   * Helper function to detect and convert strings not in UTF-8 to UTF-8.
   *
   * @param string $data
   *   The string which needs converting.
   * @param string $encoding
   *   The encoding of the CSV file.
   *
   * @return string
   *   UTF encoded string.
   */
  private static function convertEncoding($data, $encoding) {
    // Converting UTF-8 to UTF-8 will not work.
    if ($encoding == 'UTF-8') {
      return $data;
    }

    // Try to convert the data to UTF-8.
    if ($encoded_data = Unicode::convertToUtf8($data, $encoding)) {
      return $encoded_data;
    }

    // Fallback on the input data.
    return $data;
  }

  /**
   * Gets the categories from the data collected by this element.
   *
   * @param array $data
   *   The data.
   * @param string $type
   *   The chart type.
   *
   * @return array
   *   The category label and data.
   */
  public static function getCategoriesFromCollectedTable(array $data, string $type) {
    $categories_identifier = $data['table_categories_identifier'] ?? '';
    $table = $data['data_collector_table'];
    $categories = [];

    $is_first_column = $categories_identifier === self::FIRST_COLUMN;
    $first_row = current($table);
    $category_col_key = key($first_row);
    $categories['label'] = $first_row[$category_col_key];
    $data = [];
    if ($is_first_column) {
      if (!in_array($type, ['pie', 'donut'])) {
        // Extracting the categories data.
        $col_cells = array_column($table, $category_col_key);
        foreach ($col_cells as $cell) {
          $data[] = is_array($cell) ? $cell['data'] : $cell;
        }
      }
      else {
        $col_cells = array_values($first_row);
        foreach ($col_cells as $cell) {
          $data[] = is_array($cell) ? $cell['data'] : $cell;
        }
      }
    }
    else {
      $col_cells = array_values($first_row);
      foreach ($col_cells as $cell) {
        $data[] = is_array($cell) ? $cell['data'] : $cell;
      }
    }
    $categories['data'] = $data;
    // Removing the category label from categories.
    $categories_data = $categories['data'];
    array_shift($categories_data);

    $categories['data'] = $categories_data;

    return $categories;
  }

  /**
   * Gets the series from the data collected by this element.
   *
   * @param array $data
   *   The data.
   * @param string $type
   *   The type of chart.
   *
   * @return array
   *   The series.
   */
  public static function getSeriesFromCollectedTable(array $data, string $type) {
    $table = $data['data_collector_table'];
    $categories_identifier = $data['table_categories_identifier'] ?? '';

    /** @var \Drupal\charts\TypeManager $chart_type_plugin_manager */
    $chart_type_plugin_manager = \Drupal::service('plugin.manager.charts_type');
    $chart_type = $chart_type_plugin_manager->getDefinition($type);
    $is_single_axis = $chart_type['axis'] === ChartInterface::SINGLE_AXIS;

    $is_first_column = $categories_identifier === self::FIRST_COLUMN;
    $first_row = current($table);
    $category_col_key = key($first_row);

    // Skip the first row if it's considered as the  holding categories data.
    if (!$is_first_column) {
      array_shift($table);
    }

    $series = [];
    $i = 0;
    foreach ($table as $row) {
      if (!$is_first_column) {
        $name_key = key($row);
        $series[$i]['name'] = $row[$name_key]['data'] ?? [];
        $series[$i]['color'] = $row[$name_key]['color'] ?? '';
        // Removing the name from data array.
        unset($row[$name_key]);
        foreach ($row as $column) {
          // Get all the data in this column and break out of this loop.
          if ($is_single_axis) {
            if (is_numeric($column) || is_string($column)) {
              $series[$i]['data'][] = [
                $series[$i]['name'],
                self::castValueToNumeric($column),
              ];
            }
            elseif (is_array($column) && isset($column['data'])) {
              $series[$i]['data'][] = [
                $series[$i]['name'],
                self::castValueToNumeric($column['data']),
              ];
            }
          }
          else {
            if (is_numeric($column) || is_string($column)) {
              $series[$i]['data'][] = self::castValueToNumeric($column);
            }
            elseif (is_array($column) && isset($column['data'])) {
              $series[$i]['data'][] = self::castValueToNumeric($column['data']);
            }
          }
        }
        // Adding a couple types not currently supported but hopefully soon.
        if (in_array($type, [
          'scatter',
          'bubble',
          'candlestick',
          'boxplot',
        ])) {
          // Enclose the data value in an array.
          $series[$i]['data'] = [$series[$i]['data']];
        }
      }
      else {
        $j = 0;
        foreach ($row as $column_key => $column) {
          // Skipping the category label and it's data.
          if ($column_key === $category_col_key || !is_numeric($column_key)) {
            continue;
          }
          elseif ($i === 0) {
            // This is the first column which holds the data names and colors.
            $series[$j]['name'] = $column['data'] ?? $column;
            $series[$j]['color'] = $column['color'] ?? self::randomColor();
          }
          else {
            // Get all the data in this column and break out of this loop.
            $cell_value = is_array($column) && isset($column['data']) ? $column['data'] : $column;
            $cell_value = self::castValueToNumeric($cell_value);
            if ($is_single_axis) {
              $series[$j]['data'][] = [$series[$j]['name'], $cell_value];
              $series[$j]['title'][] = $row[0]['data'];
            }
            elseif (in_array($type, [
              'scatter',
              'bubble',
              'candlestick',
              'boxplot',
            ])) {
              $series[$j]['data'][0][] = $cell_value;
            }
            else {
              $series[$j]['data'][] = $cell_value;
            }
          }
          $j++;
        }
      }
      $i++;
    }

    return $series;
  }

  /**
   * Casts string value to numeric.
   *
   * @param string $value
   *   The value.
   *
   * @return float|int
   *   The numeric value.
   */
  private static function castValueToNumeric($value) {
    if (is_numeric($value)) {
      $value = is_int($value) ? (integer) $value : (float) $value;
    }
    elseif ($value === '') {
      $value = NULL;
    }
    else {
      $value = 0;
    }
    return $value;
  }

}
