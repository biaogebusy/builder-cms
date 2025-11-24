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

}
