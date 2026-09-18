<?php

namespace Drupal\captcha\Plugin\migrate\process;

use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;
use Drupal\migrate\Row;

/**
 * Perform captcha type transformation.
 *
 * @MigrateProcessPlugin(
 *   id = "captcha_type_formatter"
 * )
 *
 * To do custom value transformations use the following:
 *
 * @code
 * field_text:
 *   plugin: captcha_type_formatter
 *   source: text
 * @endcode
 */
class CaptchaTypeFormatter extends ProcessPluginBase {

  /**
   * Transforms the d7 separate captcha type and module into one row.
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    $module = $row->getSourceProperty('module') ?? 'captcha';
    $type = $row->getSourceProperty('captcha_type');
    return $module . '/' . $type;
  }

}
