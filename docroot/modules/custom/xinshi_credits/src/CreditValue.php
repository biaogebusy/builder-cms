<?php

declare(strict_types=1);

namespace Drupal\xinshi_credits;

/** Exact bounded integers and canonical identifiers shared by ledger writes. */
final class CreditValue {

  /** Parse an unsigned decimal integer without floating-point conversion. */
  public static function amount(mixed $value, bool $positive = FALSE): int {
    if (!is_string($value) || !preg_match('/^(0|[1-9][0-9]*)$/D', $value)
      || strlen($value) > strlen((string) PHP_INT_MAX)
      || (strlen($value) === strlen((string) PHP_INT_MAX) && strcmp($value, (string) PHP_INT_MAX) > 0)
      || ($positive && $value === '0')) {
      throw new CreditException('invalid_amount');
    }
    return (int) $value;
  }

  /** Reject integer overflow instead of allowing PHP to promote to float. */
  public static function add(int $left, int $right): int {
    if ($left < 0 || $right < 0 || $left > PHP_INT_MAX - $right) throw new CreditException('amount_overflow');
    return $left + $right;
  }

  /** Round a cost commitment up to whole micros. One point has 1,000 atoms. */
  public static function cost(int $atoms, int $microsPerPoint): int {
    $whole = intdiv($atoms, 1000);
    $remainder = $atoms % 1000;
    foreach ([$whole, $remainder] as $factor) {
      if ($factor && $microsPerPoint > intdiv(PHP_INT_MAX, $factor)) throw new CreditException('amount_overflow');
    }
    $tail = $remainder * $microsPerPoint;
    return self::add($whole * $microsPerPoint, intdiv($tail, 1000) + ($tail % 1000 ? 1 : 0));
  }

  /** Validate caller-owned machine identifiers without accepting control characters. */
  public static function id(mixed $value): string {
    if (!is_string($value) || !preg_match('/^[a-zA-Z0-9][a-zA-Z0-9_.:-]{0,127}$/D', $value)) {
      throw new CreditException('invalid_identifier');
    }
    return $value;
  }

  /** Validate public reasons and private audit references with bounded UTF-8 text. */
  public static function text(mixed $value, int $length = 255, bool $empty = FALSE): string {
    if (!is_string($value) || !mb_check_encoding($value, 'UTF-8') || mb_strlen($value) > $length
      || (!$empty && trim($value) === '') || preg_match('/[\x00-\x1f\x7f]/', $value)) {
      throw new CreditException('invalid_text');
    }
    return $value;
  }

  /** Keep schemas closed so an adapter cannot silently discard an approval field. */
  public static function fields(array $input, array $required, array $optional = []): void {
    if (array_diff($required, array_keys($input)) || array_diff(array_keys($input), [...$required, ...$optional])) {
      throw new CreditException('invalid_fields');
    }
  }

  /** Normalize a non-empty set of eligible service categories. */
  public static function scope(mixed $value): array {
    if (!is_array($value) || !array_is_list($value) || !$value || count($value) > 32) throw new CreditException('invalid_scope');
    $value = array_map(self::id(...), $value);
    if (count(array_unique($value)) !== count($value)) throw new CreditException('invalid_scope');
    sort($value, SORT_STRING);
    return $value;
  }

}
