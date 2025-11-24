<?php

namespace Drupal\csv_serialization\Encoder;

use Drupal\Component\Serialization\Exception\InvalidDataTypeException;
use Drupal\Component\Utility\Html;
use League\Csv\Bom;
use League\Csv\Reader;
use League\Csv\Writer;
use Symfony\Component\Serializer\Encoder\DecoderInterface;
use Symfony\Component\Serializer\Encoder\EncoderInterface;

/**
 * Adds CSV encoder support for the Serialization API.
 */
class CsvEncoder implements EncoderInterface, DecoderInterface {

  /**
   * Indicates the character used for new line. Defaults to "\n".
   *
   * @var string
   */
  protected $newline;

  /**
   * The format that this encoder supports.
   *
   * @var string
   */
  protected static $format = 'csv';

  /**
   * Indicates usage of UTF-8 signature in generated CSV file.
   *
   * @var bool
   */
  protected $useUtf8Bom = FALSE;

  /**
   * Whether to output the header row.
   *
   * @var bool
   */
  protected $outputHeader = TRUE;

  /**
   * Constructs the class.
   *
   * @param string $delimiter
   *   Indicates the character used to delimit fields. Defaults to ",".
   * @param string $enclosure
   *   Indicates the character used for field enclosure. Defaults to '"'.
   * @param string $escapeChar
   *   Indicates the character used for escaping. Defaults to "\".
   * @param bool $stripTags
   *   Whether to strip tags from values or not. Defaults to TRUE.
   * @param bool $trimValues
   *   Whether to trim values or not. Defaults to TRUE.
   */
  public function __construct(
    protected string $delimiter = ",",
    protected string $enclosure = '"',
    protected string $escapeChar = "\\",
    protected bool $stripTags = TRUE,
    protected bool $trimValues = TRUE,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function supportsEncoding(string $format):bool {
    return $format == static::$format;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsDecoding(string $format):bool {
    return $format == static::$format;
  }

  /**
   * {@inheritdoc}
   *
   * Uses HTML-safe strings, with several characters escaped.
   */
  public function encode(mixed $data, string $format, array $context = []): string {
    switch (gettype($data)) {
      case "array":
        break;

      case 'object':
        $data = (array) $data;
        break;

      // May be bool, integer, double, string, resource, NULL, or unknown.
      default:
        $data = [$data];
        break;
    }

    if (!empty($context['csv_settings'])) {
      $this->setSettings($context['csv_settings']);
    }
    elseif (!empty($context['views_style_plugin']->options['csv_settings'])) {
      $this->setSettings($context['views_style_plugin']->options['csv_settings']);
    }

    try {
      // Instantiate CSV writer with options.
      $csv = Writer::createFromFileObject(new \SplTempFileObject());
      $csv->setDelimiter($this->delimiter);
      $csv->setEnclosure($this->enclosure);
      $csv->setEscape($this->escapeChar);

      if ($this->newline) {
        $csv->setEndOfLine(stripcslashes($this->newline));
      }

      // Set data.
      if ($this->useUtf8Bom) {
        $csv->setOutputBOM(Bom::Utf8);
      }
      // Set headers.
      if ($this->outputHeader) {
        $headers = $this->extractHeaders($data, $context);
        $csv->insertOne($headers);
      }
      $csv->addFormatter([$this, 'formatRow']);
      foreach ($data as $row) {
        if (is_array($row)) {
          $csv->insertOne($row);
        }
      }
      $output = $csv->toString();

      return trim($output);
    }
    catch (\Exception $e) {
      throw new InvalidDataTypeException($e->getMessage(), $e->getCode(), $e);
    }
  }

  /**
   * Extracts the headers using the first row of values.
   *
   * @param array $data
   *   The array of data to be converted to a CSV.
   * @param array $context
   *   Options that normalizers/encoders have access to. For views encoders
   *   this means that we'll have the view available here.
   *
   *   We must make the assumption that each row shares the same set of headers
   *   will all other rows. This is inherent in the structure of a CSV.
   *
   * @return array
   *   An array of CSV headers.
   */
  protected function extractHeaders(array $data, array $context = []) {
    $headers = [];
    if (isset($data[0])) {
      $first_row = $data[0];
      $allowed_headers = array_keys($first_row);

      if (!empty($context['views_style_plugin'])) {
        $fields = $context['views_style_plugin']->view->field;
      }

      foreach ($allowed_headers as $allowed_header) {
        $headers[] = !empty($fields[$allowed_header]->options['label']) ? $fields[$allowed_header]->options['label'] : $allowed_header;
      }
    }

    return $headers;
  }

  /**
   * Formats all cells in a given CSV row.
   *
   * This flattens complex data structures into a string, and formats
   * the string.
   *
   * @param array $row
   *   A row of data. This may be a flat or multidimensional array.
   *
   * @return array
   *   A flat array of key/value, with value flattened into string.
   */
  public function formatRow(array $row) {
    $formatted_row = [];

    foreach ($row as $cell_data) {
      if (is_array($cell_data)) {
        $cell_value = $this->flattenCell($cell_data);
      }
      else {
        $cell_value = (string) $cell_data;
      }

      $formatted_row[] = $this->formatValue($cell_value);
    }

    return $formatted_row;
  }

  /**
   * Flattens a multi-dimensional array into a single level.
   *
   * @param array $data
   *   An array of data for be flattened into a cell string value.
   *
   * @return string
   *   The string value of the CSV cell, un-sanitized.
   */
  protected function flattenCell(array $data) {
    $depth = (int) $this->arrayDepth($data);

    if ($depth === 1) {
      // @todo Allow customization of this in-cell separator.
      return implode('|', $data);
    }

    $cell_value = "";
    foreach ($data as $item) {
      $cell_value .= '|' . (is_array($item) ? $this->flattenCell($item) : $item);
    }

    return trim($cell_value, '|');
  }

  /**
   * Formats a single value for a given CSV cell.
   *
   * @param string $value
   *   The raw value to be formatted.
   *
   * @return string
   *   The formatted value.
   */
  protected function formatValue($value) {
    if ($this->stripTags) {
      $value = Html::decodeEntities($value);
      $value = strip_tags($value);
    }
    if ($this->trimValues) {
      $value = trim($value);
    }

    return $value;
  }

  /**
   * {@inheritdoc}
   *
   * @return mixed
   *   The decoded data.
   *
   * @throws \League\Csv\Exception
   * @throws \League\Csv\Exception
   * @throws \League\Csv\Exception
   */
  public function decode(string $data, string $format, array $context = []): mixed {
    $csv = Reader::createFromString($data);
    $csv->setDelimiter($this->delimiter);
    $csv->setEnclosure($this->enclosure);
    $csv->setEscape($this->escapeChar);

    $results = [];
    foreach ($csv->getRecords() as $row) {
      $results[] = $this->expandRow($row);
    }

    return $results;
  }

  /**
   * Explodes multiple, concatenated values for all cells in a row.
   *
   * @param array $row
   *   The row of CSV cells.
   *
   * @return array
   *   The same row of CSV cells, with each cell's contents exploded.
   */
  public function expandRow(array $row) {
    foreach ($row as $column_name => $cell_data) {
      // @todo Allow customization of this in-cell separator.
      if (strpos($cell_data, '|') !== FALSE) {
        $row[$column_name] = explode('|', $cell_data);
      }
    }

    return $row;
  }

  /**
   * {@inheritdoc}
   */
  public static function getFileExtension() {
    return static::$format;
  }

  /**
   * Determine the depth of an array.
   *
   * This method determines array depth by analyzing the indentation of the
   * dumped array. This avoid potential issues with recursion.
   *
   * @param array $array
   *   The array to measure.
   *
   * @return float
   *   The depth of the array.
   *
   * @see http://stackoverflow.com/a/263621
   */
  protected function arrayDepth(array $array) {
    $max_indentation = 1;

    $array_str = print_r($array, TRUE);
    $lines = explode("\n", $array_str);

    foreach ($lines as $line) {
      $indentation = (strlen($line) - strlen(ltrim($line))) / 4;

      if ($indentation > $max_indentation) {
        $max_indentation = $indentation;
      }
    }

    return ceil(($max_indentation - 1) / 2) + 1;
  }

  /**
   * Set CSV settings from the Views settings array.
   *
   * This allows modules which provides integration
   * (views_data_export for example) to change default settings.
   *
   * If a tab character ('\t') is used for the delimiter, it will be properly
   * converted to "\t".
   *
   * @param array $settings
   *   Array of settings.
   *
   * @see \Drupal\views_data_export\Plugin\views\style\DataExport()
   * for list of settings.
   */
  public function setSettings(array $settings) {
    // Replace tab character with one that will be properly interpreted.
    $this->delimiter = isset($settings['delimiter']) ? str_replace('\t', "\t", $settings['delimiter']) : $this->delimiter;
    $this->enclosure = $settings['enclosure'] ?? $this->enclosure;
    $this->escapeChar = $settings['escape_char'] ?? $this->escapeChar;
    $this->useUtf8Bom = isset($settings['encoding']) ? ($settings['encoding'] === 'utf8' && !empty($settings['utf8_bom'])) : $this->useUtf8Bom;
    $this->newline = $settings['new_line'] ?? $this->newline;
    $this->stripTags = $settings['strip_tags'] ?? $this->stripTags;
    $this->trimValues = $settings['trim'] ?? $this->trimValues;
    $this->outputHeader = $settings['output_header'] ?? $this->outputHeader;
  }

}
