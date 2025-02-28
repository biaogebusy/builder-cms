<?php

namespace Drupal\xls_serialization\Encoder;

use Drupal\views\ViewExecutable;
use Drupal\Component\Serialization\Exception\InvalidDataTypeException;
use Drupal\Component\Utility\Html;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Document\Properties;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\Serializer\Encoder\EncoderInterface;

/**
 * Adds XLS encoder support for the Serialization API.
 */
class Xls implements EncoderInterface {

  /**
   * The format that this encoder supports.
   *
   * @var string
   */
  protected static $format = 'xls';

  /**
   * Format to write XLS files as.
   *
   * @var string
   */
  protected $xlsFormat = 'Xlsx';

  /**
   * Constructs an XLS encoder.
   *
   * @param string $xls_format
   *   The XLS format to use.
   */
  public function __construct($xls_format = 'Xlsx') {
    // Temporary fix until it wold be fixed at views_data_export.
    if ($xls_format == 'Excel2007') {
      $xls_format = 'Xlsx';
    }
    $this->xlsFormat = $xls_format;
  }

  /**
   * {@inheritdoc}
   */
  public function encode($data, $format, array $context = []):string {
    switch (gettype($data)) {
      case 'array':
        // Nothing to do.
        break;

      case 'object':
        $data = (array) $data;
        break;

      default:
        $data = [$data];
        break;
    }

    try {
      // Instantiate a new excel object.
      $xls = new Spreadsheet();
      $xls->setActiveSheetIndex(0);
      $sheet = $xls->getActiveSheet();

      // Set headers.
      $this->setHeaders($sheet, $data, $context);

      // Set the data.
      $this->setData($sheet, $data);

      // Set the width of every column with data in it to AutoSize.
      $this->setColumnsAutoSize($sheet);

      if (isset($context['views_style_plugin'])) {
        if (isset($context['views_style_plugin']->options['xls_settings'])) {
          $this->setSettings($context['views_style_plugin']->options['xls_settings']);
          // Set any metadata passed in via the context.
          if (isset($context['views_style_plugin']->options['xls_settings']['metadata'])) {
            $this->setMetaData($xls->getProperties(), $context['views_style_plugin']->options['xls_settings']['metadata']);
          }
        }

        if (!empty($context['views_style_plugin']->view)) {
          /** @var \Drupal\views\ViewExecutable $view */
          $view = $context['views_style_plugin']->view;
          // Set the worksheet title based on the view title within the context.
          if (!empty($view->getTitle())) {
            $sheet->setTitle($this->validateWorksheetTitle($view->getTitle()));
          }

          // Set the header row of the worksheet to bold.
          if ($view->getDisplay()->getOption('header_bold') == 1) {
            $this->setHeaderRowBold($sheet);
          }

          // Set the header row of the worksheet to italic.
          if ($view->getDisplay()->getOption('header_italic') == 1) {
            $this->setHeaderRowItalic($sheet);
          }

          // Set the background color of the header row of the worksheet.
          if ($view->getDisplay()->getOption('header_background_color') != '') {
            $this->setHeaderRowBackgroundColor($sheet, $view->getDisplay()->getOption('header_background_color'));
          }

          // Conditional formatting.
          for ($i = 0; $i <= 4; $i++) {
            $current_conditional_formatting_base_field = $view->getDisplay()->getOption('conditional_formatting_base_field_' . $i);
            if ($current_conditional_formatting_base_field !== NULL && $current_conditional_formatting_base_field !== 'Select a field') {
              $headers = $this->extractHeaders($data, $context);
              $conditional_formatting_base_field[$i] = $current_conditional_formatting_base_field;
              $field_label_or_name[$i] = $this->getViewFieldLabel($view, $conditional_formatting_base_field[$i]);
              $base_field_column_letter[$i] = $this->getColumnLetterFromFieldName($headers, $field_label_or_name[$i]);
              $operator[$i] = $this->getOperatorFromSelectIndex($view->getDisplay()->getOption('conditional_formatting_operator_' . $i));
              $compare_to[$i] = $view->getDisplay()->getOption('conditional_formatting_compare_to_' . $i);
              $rgb_background_color[$i] = $view->getDisplay()->getOption('conditional_formatting_background_color_' . $i);

              $conditional_styles[] = $this->setConditionalFormat($base_field_column_letter[$i], $operator[$i], $compare_to[$i], $rgb_background_color[$i]);
            }
          }
          if (isset($conditional_styles)) {
            $this->setConditionalFormating($sheet, $conditional_styles);
          }
        }
      }

      $writer = IOFactory::createWriter($xls, $this->xlsFormat);

      // @todo utilize a temporary file perhaps?
      // @todo This should also support batch processing.
      // @see http://stackoverflow.com/questions/9469779/how-do-i-write-my-excel-spreadsheet-into-a-variable-using-phpexcel
      ob_start();
      $writer->save('php://output');
      return ob_get_clean();
    }
    catch (\Exception $e) {
      throw new InvalidDataTypeException($e->getMessage(), $e->getCode(), $e);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function supportsEncoding($format):bool {
    return $format === static::$format;
  }

  /**
   * Set sheet headers.
   *
   * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet
   *   The worksheet to set headers for.
   * @param array $data
   *   The data array.
   * @param array $context
   *   The context options array.
   */
  protected function setHeaders(Worksheet $sheet, array $data, array $context) {
    // Extract headers from the data.
    $headers = $this->extractHeaders($data, $context);
    foreach ($headers as $column => $header) {
      $sheet->setCellValueByColumnAndRow(++$column, 1, $this->formatValue($header));
    }
  }

  /**
   * Set any available metadata.
   *
   * @param \PhpOffice\PhpSpreadsheet\Document\Properties $document_properties
   *   The document properties object.
   * @param array $metadata
   *   An associative array of metadata to set on the document. The array can
   *   contain any of the following keys (with corresponding values).
   *   - 'creator': The document creator.
   *   - 'last_modified_by': The name of the person to last modify.
   *   - 'created': The time the document was created.
   *   - 'modified': The time the document was modified.
   *   - 'title': The document title.
   *   - 'description': The document description.
   *   - 'subject': The document subject.
   *   - 'keywords': Any keywords for the document.
   *   - 'category': The document category.
   *   - 'manager': The document manager.
   *   - 'company': The company that created the document.
   *   - 'custom_properties': An associative array of property name mapping to
   *     property value. If the value is an array, the first item should be the
   *     value, and the second item the property type:
   *     - 'i': integer
   *     - 'f': floating point
   *     - 's': string (default)
   *     - 'd': 'date/time'
   *     - 'b': boolean.
   */
  protected function setMetaData(Properties $document_properties, array $metadata) {
    if (isset($metadata['creator'])) {
      $document_properties->setCreator($metadata['creator']);
    }
    if (isset($metadata['last_modified_by'])) {
      $document_properties->setLastModifiedBy($metadata['last_modified_by']);
    }
    if (isset($metadata['created'])) {
      $document_properties->setCreated($metadata['created']);
    }
    if (isset($metadata['modified'])) {
      $document_properties->setModified($metadata['modified']);
    }
    if (isset($metadata['title'])) {
      $document_properties->setTitle($metadata['title']);
    }
    if (isset($metadata['description'])) {
      $document_properties->setDescription($metadata['description']);
    }
    if (isset($metadata['subject'])) {
      $document_properties->setSubject($metadata['subject']);
    }
    if (isset($metadata['keywords'])) {
      $document_properties->setKeywords($metadata['keywords']);
    }
    if (isset($metadata['category'])) {
      $document_properties->setCategory($metadata['category']);
    }
    if (isset($metadata['manager'])) {
      $document_properties->setManager($metadata['manager']);
    }
    if (isset($metadata['company'])) {
      $document_properties->setCompany($metadata['company']);
    }

    if (isset($metadata['custom_properties'])) {
      foreach ($metadata['custom_properties'] as $name => $value) {
        $type = 's';
        if (is_array($value)) {
          $type = array_pop($value);
          $value = reset($value);
        }
        $document_properties->setCustomProperty($name, $value, $type);
      }
    }
  }

  /**
   * Set sheet data.
   *
   * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet
   *   The worksheet to put the data in.
   * @param array $data
   *   The data to be put in the worksheet.
   */
  protected function setData(Worksheet $sheet, array $data) {
    foreach ($data as $i => $row) {
      $column = 1;
      foreach ($row as $value) {
        // Since headers have been added, rows are offset here by 2.
        $sheet->setCellValueByColumnAndRow($column, $i + 2, $this->formatValue($value));
        $column++;
      }
    }
  }

  /**
   * Formats a single value for a given XLS cell.
   *
   * @param string $value
   *   The raw value to be formatted.
   *
   * @return string
   *   The formatted value.
   */
  protected function formatValue($value) {
    // @todo Make these filters configurable.
    $value = Html::decodeEntities($value);
    $value = strip_tags($value);
    $value = trim($value);

    return $value;
  }

  /**
   * Extract the headers from the data array.
   *
   * @param array $data
   *   The data array.
   * @param array $context
   *   The context options array.
   *
   * @return string[]
   *   An array of headers to be used.
   */
  protected function extractHeaders(array $data, array $context) {
    $headers = [];
    if ($first_row = reset($data)) {
      if (isset($context['header'])) {
        $headers = $context['header'];
      }
      elseif (isset($context['views_style_plugin'])) {
        /** @var \Drupal\views\ViewExecutable $view */
        $view = $context['views_style_plugin']->view;
        $fields = $view->field;
        foreach ($first_row as $key => $value) {
          $headers[] = !empty($fields[$key]->options['label']) ? $fields[$key]->options['label'] : $key;
        }
      }
      else {
        $headers = array_keys($first_row);
      }
    }

    return $headers;
  }

  /**
   * Set XLS settings from the Views settings array.
   *
   * @param array $settings
   *   An array of XLS settings.
   */
  protected function setSettings(array $settings) {
    // Temporary fix this until it would be fixed at the views_data_export.
    if ($settings['xls_format'] == 'Excel2007') {
      $settings['xls_format'] = 'Xlsx';
    }
    if ($settings['xls_format'] == 'Excel5') {
      $settings['xls_format'] = 'Xls';
    }
    $this->xlsFormat = $settings['xls_format'];
  }

  /**
   * Set width of all columns with data in them in sheet to AutoSize.
   *
   * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet
   *   The worksheet to set the column width to AutoSize for.
   */
  protected function setColumnsAutoSize(Worksheet $sheet) {
    foreach ($sheet->getColumnIterator() as $column) {
      $column_index = $column->getColumnIndex();
      $sheet->getColumnDimension($column_index)->setAutoSize(TRUE);
    }
  }

  /**
   * Set font of the header (first) row to bold.
   *
   * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet
   *   The worksheet to set the font of the header row to bold.
   */
  protected function setHeaderRowBold(Worksheet $sheet) {
    $sheet->getStyle('A1:' . $sheet->getHighestDataColumn() . '1')->getFont()->setBold(TRUE);
  }

  /**
   * Set font of the header (first) row to italic.
   *
   * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet
   *   The worksheet to set the font of the header row to italic.
   */
  protected function setHeaderRowItalic(Worksheet $sheet) {
    $sheet->getStyle('A1:' . $sheet->getHighestDataColumn() . '1')->getFont()->setItalic(TRUE);
  }

  /**
   * Set background color of the header (first) row.
   *
   * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet
   *   The worksheet to set the background color of the header row.
   * @param string $rgb
   *   The worksheet to set the background color of the header row.
   *
   * @throws \PhpOffice\PhpSpreadsheet\Exception
   */
  protected function setHeaderRowBackgroundColor(Worksheet $sheet, $rgb) {
    $style = [
      'fill' => [
        'fillType' => Fill::FILL_SOLID,
        'startColor' => [
          'argb' => 'FF' . $rgb,
        ],
        'endColor' => [
          'argb' => 'FF' . $rgb,
        ],
      ],
    ];

    $sheet->getStyle('A1:' . $sheet->getHighestDataColumn() . '1')->applyFromArray($style);
  }

  /**
   * Gets conditional formatting for whole row if the comparison is TRUE.
   *
   * @param string $column_letter
   *   The column letter that contains the cells to compare against.
   * @param string $operator
   *   The operator to use in the comparison.
   * @param string $compare_to
   *   The value to compare to.
   * @param string $rgb_background_color
   *   The RGB value of the background color to set on the whole row,
   *   if the comparison is TRUE.
   *
   * @return \PhpOffice\PhpSpreadsheet\Style\Conditional
   *   The conditional formatting.
   */
  protected function setConditionalFormat($column_letter, $operator, $compare_to, $rgb_background_color) {
    $conditional_format = new Conditional();
    $conditional_format->setConditionType(Conditional::CONDITION_EXPRESSION);
    $conditional_format->addCondition('=$' . $column_letter . '2' . $operator . '"' . $compare_to . '"');
    $conditional_format->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getEndColor()->setRGB($rgb_background_color);

    return $conditional_format;
  }

  /**
   * Sets conditional formats on worksheet.
   *
   * @param \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet $sheet
   *   The sheet to set the conditional formats on.
   * @param array $conditional_styles
   *   The conditional formats to set.
   *
   * @throws \PhpOffice\PhpSpreadsheet\Exception
   */
  protected function setConditionalFormating(Worksheet $sheet, array $conditional_styles) {
    $highest_data_column = $sheet->getHighestDataColumn();
    $highest_data_row = $sheet->getHighestDataRow();
    $current_conditional_styles = $sheet->getStyle('A2')->getConditionalStyles();
    $conditional_styles = array_merge($current_conditional_styles, $conditional_styles);

    $sheet->getStyle('$A$2:$' . $highest_data_column . '$' . $highest_data_row)->setConditionalStyles($conditional_styles);
  }

  /**
   * Get the column letter for a field name from the headers.
   *
   * @param array $headers
   *   The header of the worksheet.
   * @param string $field_label_or_name
   *   The field label or field name to get the column letter for.
   *
   * @return string
   *   The column letter
   */
  protected function getColumnLetterFromFieldName(array $headers, $field_label_or_name) {
    $base_field_header_index_num = array_search($field_label_or_name, $headers) + 1;

    return Coordinate::stringFromColumnIndex($base_field_header_index_num);
  }

  /**
   * Get the label in the view for a field name (if available)
   *
   * @param \Drupal\views\ViewExecutable $view
   *   The view to get the field labels from.
   * @param string $field_name
   *   The field name to get the view label for.
   *
   * @return string
   *   The label in the view if available, field name otherwise.
   */
  protected function getViewFieldLabel(ViewExecutable $view, $field_name) {
    return isset($view->field[$field_name]->options['label']) ? $view->field[$field_name]->options['label'] : $field_name;
  }

  /**
   * Returns the operator for conditional formatting from the HTML-select index.
   *
   * @param string $operator_select_index
   *   The index from the HTML-select for operator.
   *
   * @return string
   *   The operator as a string.
   */
  protected function getOperatorFromSelectIndex($operator_select_index) {
    $operator_options = [0 => '=', 1 => '<>'];

    return $operator_options[$operator_select_index];
  }

  /**
   * Validates the title of the Worksheet to ensure it's valid.
   *
   * Worksheet titles must not exceed 31 characters,
   * contain:- ":" "\"  "/"  "?"  "*"  "["  "]"
   * or be blank.
   *
   * @param string $title
   *   The orginal worksheet value.
   *
   * @return string
   *   The validated worksheet title
   */
  protected function validateWorksheetTitle($title) {
    $title = preg_replace('[:\\*/\[\]?]', '', $title);

    return substr($title, 0, 30);
  }

}
