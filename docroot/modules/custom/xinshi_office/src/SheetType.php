<?php

declare(strict_types=1);

namespace Drupal\xinshi_office;

/** Validates portable SheetContent v1 independently of the browser and AI adapters. */
final class SheetType implements OfficeTypeInterface {

  private const BORDERS = ['thin', 'hair', 'dotted', 'dashed', 'dashDot', 'dashDotDot', 'double',
    'medium', 'mediumDashed', 'mediumDashDot', 'mediumDashDotDot', 'slantDashDot', 'thick'];

  /** {@inheritdoc} */
  public function kind(): string { return 'sheet'; }

  /** {@inheritdoc} */
  public function fileFormat(): array {
    return ['extension' => '.xlsx', 'part' => 'xl/workbook.xml',
      'contentType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml',
      'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
  }

  /** {@inheritdoc} */
  public function validate(mixed $content): array {
    OfficeInput::object($content, ['version', 'type', 'dateSystem', 'styles', 'sheets']);
    if ($content->version !== 1 || $content->type !== 'workbook' ||
      !in_array($content->dateSystem, ['1900', '1904'], TRUE) || !$content->styles instanceof \stdClass) self::fail();
    $styles = get_object_vars($content->styles);
    if (count($styles) > 512) self::fail();
    foreach ($styles as $id => $style) { self::id((string) $id); self::style($style); }
    $styleRef = static function (mixed $id) use ($styles): void {
      self::id($id);
      if (!array_key_exists($id, $styles)) self::fail();
    };
    self::items($content->sheets, 10, 1);
    $ids = $names = $formulas = [];
    $cellCount = $characters = $grid = $mergeCount = $references = 0;
    $visible = FALSE;
    foreach ($content->sheets as $sheet) {
      OfficeInput::object($sheet, ['id', 'name', 'rows', 'columns', 'defaultRowHeight',
        'defaultColumnWidth', 'cells', 'merges', 'rowSizes', 'columnSizes', 'freeze'], ['filter', 'filters', 'hidden']);
      self::id($sheet->id);
      OfficeInput::text($sheet->name, 31, TRUE);
      if (preg_match('~[\[\]\\\\/*?:\r\n\t]~u', $sheet->name) ||
        str_starts_with($sheet->name, "'") || str_ends_with($sheet->name, "'")) self::fail();
      $name = mb_strtolower($sheet->name, 'UTF-8');
      if (isset($ids[$sheet->id]) || isset($names[$name])) self::fail();
      $ids[$sheet->id] = TRUE; $names[$name] = $sheet;
      self::integer($sheet->rows, 1, 10000); self::integer($sheet->columns, 1, 256);
      $grid += $sheet->rows * $sheet->columns;
      if ($grid > 1000000) self::fail();
      self::number($sheet->defaultRowHeight, 1, 409);
      self::number($sheet->defaultColumnWidth, 1, 255);
      if (property_exists($sheet, 'hidden') && !is_bool($sheet->hidden)) self::fail();
      $visible = $visible || !($sheet->hidden ?? FALSE);
      OfficeInput::object($sheet->freeze, ['rows', 'columns']);
      self::integer($sheet->freeze->rows, 0, $sheet->rows - 1);
      self::integer($sheet->freeze->columns, 0, $sheet->columns - 1);
      if (property_exists($sheet, 'filter')) self::range($sheet->filter, $sheet);
      if (property_exists($sheet, 'filters')) {
        if (!isset($sheet->filter)) self::fail();
        self::items($sheet->filters, $sheet->columns);
        $filtered_columns = [];
        foreach ($sheet->filters as $criterion) {
          OfficeInput::object($criterion, ['column', 'values', 'blank']);
          self::integer($criterion->column, $sheet->filter->startColumn, $sheet->filter->endColumn);
          if (isset($filtered_columns[$criterion->column]) || !is_bool($criterion->blank)) self::fail();
          $filtered_columns[$criterion->column] = TRUE;
          self::items($criterion->values, 1000);
          foreach ($criterion->values as $filter_value) {
            OfficeInput::text($filter_value, 32767);
            $characters += self::length($filter_value);
          }
          if (count(array_unique($criterion->values, SORT_STRING)) !== count($criterion->values) || $characters > 1000000) self::fail();
        }
      }
      foreach ([[$sheet->rowSizes, $sheet->rows, 409], [$sheet->columnSizes, $sheet->columns, 255]] as [$dimensions, $count, $maximum]) {
        self::items($dimensions, $count);
        $seen = [];
        foreach ($dimensions as $dimension) {
          OfficeInput::object($dimension, ['index'], ['size', 'hidden', 'style']);
          self::integer($dimension->index, 0, $count - 1);
          if (isset($seen[$dimension->index]) || count(get_object_vars($dimension)) < 2) self::fail();
          $seen[$dimension->index] = TRUE;
          if (property_exists($dimension, 'size')) self::number($dimension->size, 1, $maximum);
          if (property_exists($dimension, 'hidden') && !is_bool($dimension->hidden)) self::fail();
          if (property_exists($dimension, 'style')) $styleRef($dimension->style);
        }
      }
      self::items($sheet->merges, 1000);
      $mergeCount += count($sheet->merges);
      if ($mergeCount > 1000) self::fail();
      foreach ($sheet->merges as $index => $range) {
        self::range($range, $sheet);
        if ($range->startRow === $range->endRow && $range->startColumn === $range->endColumn) self::fail();
        for ($previous = 0; $previous < $index; $previous++) {
          $other = $sheet->merges[$previous];
          if ($range->startRow <= $other->endRow && $range->endRow >= $other->startRow &&
            $range->startColumn <= $other->endColumn && $range->endColumn >= $other->startColumn) self::fail();
        }
      }
      self::items($sheet->cells, 50000);
      $coordinates = [];
      foreach ($sheet->cells as $cell) {
        if (++$cellCount > 50000) self::fail();
        OfficeInput::object($cell, ['row', 'column'], ['value', 'style']);
        self::integer($cell->row, 0, $sheet->rows - 1); self::integer($cell->column, 0, $sheet->columns - 1);
        $coordinate = $cell->row * $sheet->columns + $cell->column;
        if (isset($coordinates[$coordinate])) self::fail();
        $coordinates[$coordinate] = TRUE;
        if (property_exists($cell, 'style')) $styleRef($cell->style);
        if (!property_exists($cell, 'value')) {
          if (!property_exists($cell, 'style')) self::fail();
          continue;
        }
        foreach ($sheet->merges as $range) {
          if ($cell->row >= $range->startRow && $cell->row <= $range->endRow &&
            $cell->column >= $range->startColumn && $cell->column <= $range->endColumn &&
            ($cell->row !== $range->startRow || $cell->column !== $range->startColumn)) self::fail();
        }
        $value = $cell->value;
        OfficeInput::object($value, ['type'], ['value', 'formula']);
        if ($value->type === 'formula') {
          OfficeInput::object($value, ['type', 'formula']);
          OfficeInput::text($value->formula, 8192);
          $characters += self::length($value->formula);
          $formulas[] = [$sheet, $value->formula];
        }
        else {
          OfficeInput::object($value, ['type', 'value']);
          switch ($value->type) {
            case 'text':
              OfficeInput::text($value->value, 32767);
              $characters += self::length($value->value);
              break;
            case 'number': self::number($value->value, -PHP_FLOAT_MAX, PHP_FLOAT_MAX); break;
            case 'date': self::number($value->value, 0, $content->dateSystem === '1900' ? 2958465.99999 : 2957003.99999); break;
            case 'boolean': if (!is_bool($value->value)) self::fail(); break;
            case 'error': if (!in_array($value->value, SheetFormula::ERRORS, TRUE)) self::fail(); break;
            default: self::fail();
          }
        }
        if ($characters > 1000000) self::fail();
      }
    }
    if (!$visible) self::fail();
    foreach ($formulas as [$sheet, $formula]) {
      foreach (SheetFormula::references($formula) as $reference) {
        $target = $reference['sheet'] === NULL ? $sheet : ($names[mb_strtolower($reference['sheet'], 'UTF-8')] ?? NULL);
        if (!$target || $reference['endRow'] >= $target->rows || $reference['endColumn'] >= $target->columns) self::fail();
        $references += ($reference['endRow'] - $reference['startRow'] + 1) *
          ($reference['endColumn'] - $reference['startColumn'] + 1);
        if ($references > 1000000) self::fail();
      }
    }
    return [];
  }

  private static function style(mixed $style): void {
    OfficeInput::object($style, [], ['font', 'fontSize', 'bold', 'italic', 'underline', 'strike',
      'color', 'fill', 'borders', 'horizontal', 'vertical', 'wrap', 'numFmt']);
    foreach (get_object_vars($style) as $key => $value) {
      if (in_array($key, ['bold', 'italic', 'underline', 'strike', 'wrap'], TRUE)) {
        if (!is_bool($value)) self::fail();
      }
      elseif ($key === 'font') {
        OfficeInput::text($value, 100, TRUE);
        if (preg_match('/[\r\n\t]/', $value)) self::fail();
      }
      elseif ($key === 'fontSize') self::number($value, 1, 96);
      elseif (in_array($key, ['color', 'fill'], TRUE)) self::color($value);
      elseif ($key === 'horizontal') {
        if (!in_array($value, ['left', 'center', 'right'], TRUE)) self::fail();
      }
      elseif ($key === 'vertical') {
        if (!in_array($value, ['top', 'middle', 'bottom'], TRUE)) self::fail();
      }
      elseif ($key === 'numFmt') {
        OfficeInput::text($value, 256);
        if ($value === '') self::fail();
      }
      elseif ($key === 'borders') {
        OfficeInput::object($value, [], ['top', 'right', 'bottom', 'left']);
        foreach (get_object_vars($value) as $border) {
          OfficeInput::object($border, ['style', 'color']);
          if (!in_array($border->style, self::BORDERS, TRUE)) self::fail();
          self::color($border->color);
        }
      }
    }
  }

  private static function range(mixed $range, \stdClass $sheet): void {
    OfficeInput::object($range, ['startRow', 'endRow', 'startColumn', 'endColumn']);
    self::integer($range->startRow, 0, $sheet->rows - 1);
    self::integer($range->endRow, $range->startRow, $sheet->rows - 1);
    self::integer($range->startColumn, 0, $sheet->columns - 1);
    self::integer($range->endColumn, $range->startColumn, $sheet->columns - 1);
  }

  private static function id(mixed $id): void {
    if (!is_string($id) || !preg_match('/^[A-Za-z0-9_-]{1,64}$/D', $id) ||
      in_array($id, ['__proto__', 'constructor', 'prototype'], TRUE)) self::fail();
  }

  private static function color(mixed $value): void {
    if (!is_string($value) || !preg_match('/^#[0-9A-F]{6}$/D', $value)) self::fail();
  }

  private static function items(mixed $value, int $maximum, int $minimum = 0): void {
    if (!is_array($value) || !array_is_list($value) || count($value) > $maximum || count($value) < $minimum) self::fail();
  }

  private static function number(mixed $value, float $minimum, float $maximum): void {
    if ((!is_int($value) && !is_float($value)) || !is_finite((float) $value) || $value < $minimum || $value > $maximum) self::fail();
  }

  private static function integer(mixed $value, int $minimum, int $maximum): void {
    self::number($value, $minimum, $maximum);
    if (floor($value) !== (float) $value) self::fail();
  }

  private static function length(string $value): int {
    return (int) (strlen(mb_convert_encoding($value, 'UTF-16LE', 'UTF-8')) / 2);
  }

  private static function fail(): never { throw new OfficeException('invalid_content'); }

}
