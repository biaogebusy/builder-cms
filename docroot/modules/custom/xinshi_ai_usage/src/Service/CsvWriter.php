<?php

declare(strict_types=1);

namespace Drupal\xinshi_ai_usage\Service;

/**
 * CSV encoding for report exports.
 *
 * RFC 4180 quoting with a UTF-8 BOM so spreadsheets open Chinese text
 * correctly. Cells that a spreadsheet would evaluate as a formula (leading
 * `=`, `+`, `-`, `@`, tab or CR) are prefixed with an apostrophe unless they
 * are plain numbers, so an operation id or model name can never execute.
 */
final class CsvWriter {

  public const BOM = "\xEF\xBB\xBF";
  private const NUMBER = '/^-?\d+(\.\d+)?\z/';
  private const FORMULA_LEAD = ['=', '+', '-', '@', "\t", "\r"];

  /** One encoded line including the CRLF terminator. */
  public static function row(array $cells): string {
    return implode(',', array_map([self::class, 'cell'], $cells)) . "\r\n";
  }

  /** One encoded cell; NULL and booleans become empty / 1 / 0. */
  public static function cell(mixed $value): string {
    if ($value === NULL) {
      return '';
    }
    if (is_bool($value)) {
      return $value ? '1' : '0';
    }
    $text = (string) $value;
    if ($text !== '' && !preg_match(self::NUMBER, $text) && in_array($text[0], self::FORMULA_LEAD, TRUE)) {
      $text = "'" . $text;
    }
    if (preg_match('/[",\r\n\t]/', $text)) {
      $text = '"' . str_replace('"', '""', $text) . '"';
    }
    return $text;
  }

}
