<?php

namespace Drupal\xls_serialization\Encoder;

/**
 * Adds XLSX encoder support for the Serialization API.
 */
class Xlsx extends Xls {

  /**
   * The format that this encoder supports.
   *
   * @var string
   */
  protected static $format = 'xlsx';

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
    $this->xlsFormat = $xls_format;
  }

  /**
   * {@inheritdoc}
   */
  protected function setSettings(array $settings) {}

}
