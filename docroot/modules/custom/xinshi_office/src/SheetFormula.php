<?php

declare(strict_types=1);

namespace Drupal\xinshi_office;

/** Parses the same bounded, non-executing formula subset as the project browser model. */
final class SheetFormula {

  public const ERRORS = ['#NULL!', '#DIV/0!', '#VALUE!', '#REF!', '#NAME?', '#NUM!', '#N/A'];

  /** Returns referenced rectangles; this service never computes values or follows URLs. */
  public static function references(string $formula): array {
    OfficeInput::text($formula, 8192);
    if (!str_starts_with($formula, '=')) self::fail();
    $pattern = '~\G(?:\s+|"(?:[^"]|"")*"|\'(?:[^\']|\'\')*\'|\#[A-Z0-9/]+[!?]?|(?:\d+(?:\.\d*)?|\.\d+)(?:[Ee][+-]?\d+)?|\$?[A-Za-z]+\$[1-9][0-9]*|\$[A-Za-z]+[1-9][0-9]*|[\p{L}_][\p{L}\p{N}_.]*|<=|>=|<>|[+\-*/^%&=<>(),:!])~u';
    $offset = 1;
    $tokens = [];
    while ($offset < strlen($formula)) {
      if (!preg_match($pattern, $formula, $match, 0, $offset)) self::fail();
      $offset += strlen($match[0]);
      if (!preg_match('/^\s+$/u', $match[0])) $tokens[] = $match[0];
      if (count($tokens) > 1024) self::fail();
    }
    $cursor = $depth = 0;
    $references = [];
    $take = static function (string $expected) use (&$cursor, $tokens): bool {
      if (($tokens[$cursor] ?? NULL) !== $expected) return FALSE;
      $cursor++;
      return TRUE;
    };
    $expression = static function () use (&$expression, &$operand, &$depth, &$cursor, $tokens): void {
      if (++$depth > 32) self::fail();
      $operand();
      while (in_array($tokens[$cursor] ?? NULL, ['+', '-', '*', '/', '^', '&', '=', '<>', '<', '>', '<=', '>='], TRUE)) {
        $cursor++;
        $operand();
      }
      $depth--;
    };
    $operand = static function () use (&$operand, $expression, &$depth, &$cursor, $tokens, $take, &$references): void {
      if ($take('+') || $take('-')) {
        if (++$depth > 32) self::fail();
        $operand();
        $depth--;
      }
      elseif ($take('(')) {
        $expression();
        if (!$take(')')) self::fail();
      }
      else {
        $value = $tokens[$cursor++] ?? '';
        if ($value === '') self::fail();
        if (str_starts_with($value, '"') || preg_match('/^(?:\d|\.\d)/', $value) || in_array($value, self::ERRORS, TRUE)) {
          if (preg_match('/^(?:\d|\.\d)/', $value) && !is_finite((float) $value)) self::fail();
        }
        elseif (($tokens[$cursor] ?? NULL) === '(') {
          if (!in_array(strtoupper($value), ['SUM', 'AVERAGE', 'COUNT', 'MIN', 'MAX', 'IF', 'ROUND'], TRUE)) self::fail();
          $cursor++;
          if (!$take(')')) {
            $expression();
            while ($take(',')) $expression();
            if (!$take(')')) self::fail();
          }
        }
        elseif (in_array(strtoupper($value), ['TRUE', 'FALSE'], TRUE) && ($tokens[$cursor] ?? NULL) !== '!') {
          // Boolean literals cannot name a cell unless followed by a worksheet qualifier.
        }
        else {
          $sheet = NULL;
          if ($take('!')) {
            $sheet = str_starts_with($value, "'") ? str_replace("''", "'", substr($value, 1, -1)) : $value;
            OfficeInput::text($sheet, 31, TRUE);
            if (preg_match('~[\[\]\\\\/*?:]~u', $sheet)) self::fail();
            $value = $tokens[$cursor++] ?? '';
          }
          $first = self::address($value);
          $last = $take(':') ? self::address($tokens[$cursor++] ?? '') : $first;
          $references[] = ['sheet' => $sheet, 'startRow' => min($first[0], $last[0]),
            'endRow' => max($first[0], $last[0]), 'startColumn' => min($first[1], $last[1]),
            'endColumn' => max($first[1], $last[1])];
        }
      }
      $take('%');
    };
    $expression();
    if ($cursor !== count($tokens)) self::fail();
    return $references;
  }

  private static function address(string $value): array {
    if (!preg_match('/^\$?([A-Za-z]{1,3})\$?([1-9][0-9]{0,4})$/D', $value, $match)) self::fail();
    $column = 0;
    foreach (str_split(strtoupper($match[1])) as $letter) $column = $column * 26 + ord($letter) - 64;
    $row = (int) $match[2];
    if ($column > 256 || $row > 10000) self::fail();
    return [$row - 1, $column - 1];
  }

  private static function fail(): never {
    throw new OfficeException('invalid_formula');
  }

}
