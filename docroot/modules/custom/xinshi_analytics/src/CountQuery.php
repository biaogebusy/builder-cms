<?php

declare(strict_types=1);

namespace Drupal\xinshi_analytics;

/** Strict explicit-scope protocol validation, independent of entity storage and site defaults. */
final class CountQuery {

  public const MAX_INTEGER = 9007199254740991;

  /** Rejects extras instead of silently accepting a different query. */
  public static function keys(mixed $value, array $keys): bool {
    return is_array($value) && count($value) === count($keys)
      && !array_diff($keys, array_keys($value));
  }

  /** Public aliases never select property paths or SQL expressions. */
  public static function alias(mixed $value): bool {
    return is_string($value) && preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $value) === 1;
  }

  /** Limits match the Node protocol, including Unicode labels and enum values. */
  public static function text(mixed $value): bool {
    return is_string($value) && mb_strlen($value) >= 1 && mb_strlen($value) <= 200;
  }

  /** Language selection is explicit and never falls back to another translation. */
  public static function language(mixed $value): bool {
    return is_string($value) && preg_match('/^[a-z]{2,3}(-[a-z0-9]{2,8})*$/D', $value) === 1;
  }

  /** Offset strings cannot stand in for a named timezone with calendar rules. */
  public static function timezone(mixed $value): bool {
    return is_string($value) && strlen($value) <= 100
      && in_array($value, \DateTimeZone::listIdentifiers(\DateTimeZone::ALL_WITH_BC), TRUE);
  }

  /** Numeric strings and unsafe JavaScript integers are not protocol integers. */
  public static function integer(mixed $value, int $maximum = self::MAX_INTEGER): bool {
    return is_int($value) && $value >= 1 && $value <= $maximum;
  }

  /** Enumerations are finite, unique lists, including when decoded from JSON. */
  public static function strings(mixed $value, int $max, int $min = 0): bool {
    return is_array($value) && array_is_list($value) && count($value) >= $min
      && count($value) <= $max && count(array_filter($value, 'is_string')) === count($value)
      && count(array_unique($value, SORT_STRING)) === count($value);
  }

  /** PHP's date normalizations must not turn impossible dates into valid queries. */
  public static function instant(mixed $value): \DateTimeImmutable {
    if (!is_string($value) || !preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/D', $value)) {
      throw new AnalyticsException('invalid_query');
    }
    $date = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $value, new \DateTimeZone('UTC'));
    if (!$date || $date->format('Y-m-d\TH:i:s\Z') !== $value) {
      throw new AnalyticsException('invalid_query');
    }
    return $date;
  }

  /** Validates the same required fields and count-only operation as the Node tools. */
  public static function validate(array $query): void {
    if (!self::keys($query, ['datasetId', 'datasetVersion', 'dimensions', 'filters',
      'scope', 'timezone', 'language'])
      || !self::alias($query['datasetId'])
      || !self::integer($query['datasetVersion']) || !self::strings($query['dimensions'], 2)
      || count(array_filter($query['dimensions'], self::alias(...))) !== count($query['dimensions'])
      || !is_array($query['filters']) || !array_is_list($query['filters']) || count($query['filters']) > 10
      || !self::timezone($query['timezone']) || !self::language($query['language'])) {
      throw new AnalyticsException('invalid_query');
    }
    $scope = $query['scope'];
    $all = is_array($scope) && ($scope['kind'] ?? NULL) === 'all';
    $keys = $all ? ['kind'] : ['kind', 'from', 'toExclusive'];
    if (!self::keys($scope, $keys)
      || (!in_array($scope['kind'], ['all', 'range'], TRUE))
      || (!$all && self::instant($scope['from']) >= self::instant($scope['toExclusive']))) {
      throw new AnalyticsException('invalid_query');
    }
    $seen = [];
    foreach ($query['filters'] as $filter) {
      $eq = is_array($filter) && ($filter['operator'] ?? NULL) === 'eq';
      if (!self::keys($filter, ['field', 'operator', $eq ? 'value' : 'values'])
        || !self::alias($filter['field']) || isset($seen[$filter['field']])
        || !in_array($filter['operator'], ['eq', 'in'], TRUE)) {
        throw new AnalyticsException('invalid_query');
      }
      $values = $eq ? [$filter['value']] : $filter['values'];
      if (!self::strings($values, 100, 1)
        || count(array_filter($values, self::text(...))) !== count($values)) {
        throw new AnalyticsException('invalid_query');
      }
      $seen[$filter['field']] = TRUE;
    }
  }

}
