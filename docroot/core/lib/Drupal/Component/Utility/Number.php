<?php

namespace Drupal\Component\Utility;

/**
 * Provides helper methods for manipulating numbers.
 *
 * @ingroup utility
 */
class Number {

  /**
   * Normalizes a numeric value to a lossless, easily parseable numeric string.
   *
   * The normalized value is string suitable for use with libraries such as
   * BCMath (https://secure.php.net/manual/en/book.bc.php).
   *
   * @param int|float|string $number
   *   The value to normalize. If this is a string, it must be formatted as an
   *   integer or a float. Floats with a higher number of significant decimals
   *   than precision value from the PHP runtime configuration [default: 14]
   *   will lose the additional precision as PHP does not guarantee.
   *
   * @return string
   *   The normalized numeric string.
   */
  public static function normalize($number) {
    // Convert non-strings to strings, for consistent and lossless processing.
    if (is_float($number)) {
      // If the float has less significant decimals than the number we can
      // guarantee, convert it to a string directly.
      if (preg_match(sprintf('/^\d+\.\d{1,%d}$/', ini_get('precision')), (string) $number)) {
        return (string) $number;
      }
      // For floats with more significant decimals than the number we can
      // guarantee, discard the not guaranteed ones.
      return rtrim(number_format($number, ini_get('precision'), '.', ''), '0');
    }
    return (string) $number;
  }

  /**
   * Counts a number's significant decimals.
   *
   * @param int|float|string $number
   *   The number whose decimals needed to be to count. If this is a string, it
   *   must be an integer or a float formatted. Floats are limited to the
   *   precision guaranteed by PHP (for example, 15).
   *
   * @return int
   *   Number of significant decimal digits. Floats are limited to the precision
   *   guaranteed by PHP (for example, 15). Numeric strings do not suffer from
   *   the system-specific limitations to float precision, so they can contain
   *   many more significant decimals.
   *
   *   Number::countSignificantDecimals(100.12345678901234567890) returns 11 but
   *   Number::countSignificantDecimals('100.12345678901234567890') returns 20.
   */
  public static function countSignificantDecimals($number) {
    $number = static::normalize($number);

    // If no decimal separator is encountered, the step is an integer and the
    // there are 0 significant decimals.
    if (strrpos($number, '.') === FALSE) {
      return 0;
    }

    // If a decimal separator is encountered, count the number of significant
    // decimal digits.
    return strlen($number) - strrpos($number, '.') - 1;
  }

  /**
   * Verifies that a number is a multiple of a given step.
   *
   * The implementation assumes it is dealing with IEEE 754 double precision
   * floating point numbers that are used by PHP on most systems.
   *
   * This is based on the number/range verification methods of webkit.
   *
   * Besides integers and floating numbers, we also support decimal numbers
   * which are not stored in IEEE 754 format. In somewhat higher precisions for
   * these numbers, the $step value cannot accurately represent the desired
   * precision, when it is passed as a float. Passing it as a string bypasses
   * this loss of precision and enables a correct calculation of the step
   * validity.
   *
   * @param int|float|string $value
   *   The value that needs to be checked. If this is a string, it must be
   *   formatted as an integer or a float.
   * @param int|float|string $step
   *   The step scale factor. Must be positive. If this is a string, it must be
   *   formatted as an integer or a float.
   * @param float|null $offset
   *   (optional) An offset, to which the difference must be a multiple of the
   *   given step.
   *
   * @return bool
   *   TRUE if no step mismatch has occurred, or FALSE otherwise.
   *
   * @see http://opensource.apple.com/source/WebCore/WebCore-1298/html/NumberInputType.cpp
   */
  public static function validStep($value, $step, $offset = 0.0) {
    // Confirm the step is positive.
    if ($step <= 0) {
      return FALSE;
    }

    // Convert the value to a float so we can evaluate the precision later.
    // Because subtracting the offset may change the value's precision, we only
    // do so if it was set explicitly (is not null).
    $float_value = (float) abs($value - $offset);

    // The expected number significant decimals is dictated by the step.
    $expected_significant_decimals = static::countSignificantDecimals($step) + 1;

    // If the actual value has more significant decimals than expected, it has a
    // higher precision than desired it isn't divisible by the step.
    $actual_significant_decimals = static::countSignificantDecimals($float_value);
    if ($actual_significant_decimals > $expected_significant_decimals) {
      return FALSE;
    }

    $float_value = (float) round($float_value, $expected_significant_decimals);

    // The fractional part of a double has 53 bits. The greatest number that
    // could be represented with that is 2^53. If the given value is even bigger
    // than $step * 2^53, then dividing by $step will result in a very small
    // remainder. Since that remainder can't even be represented with a single
    // precision float the following computation of the remainder makes no sense
    // and we can safely ignore it instead.
    if ($float_value / pow(2.0, 53) > $step) {
      return TRUE;
    }

    $expected_float_value = (float) round($step * round($float_value / $step), $expected_significant_decimals);
    // Now compute that remainder of a division by $step.
    $remainder = (float) abs($float_value - $expected_float_value);

    // $remainder is a double precision floating point number. Remainders that
    // can't be represented with single precision floats are acceptable. The
    // fractional part of a float has 24 bits. That means remainders smaller than
    // $step * 2^-24 are acceptable.
    $computed_acceptable_error = (float) ($step / pow(2.0, 24));

    return $computed_acceptable_error >= $remainder || $remainder >= ($step - $computed_acceptable_error);
  }

  /**
   * Generates a sorting code from an integer.
   *
   * Consists of a leading character indicating length, followed by N digits
   * with a numerical value in base 36 (alphadecimal). These codes can be sorted
   * as strings without altering numerical order.
   *
   * It goes:
   * 00, 01, 02, ..., 0y, 0z,
   * 110, 111, ... , 1zy, 1zz,
   * 2100, 2101, ..., 2zzy, 2zzz,
   * 31000, 31001, ...
   *
   * @param int $i
   *   The integer value to convert.
   *
   * @return string
   *   The alpha decimal value.
   *
   * @see \Drupal\Component\Utility\Number::alphadecimalToInt
   */
  public static function intToAlphadecimal($i = 0) {
    $num = base_convert((int) $i, 10, 36);
    $length = strlen($num);

    return chr($length + ord('0') - 1) . $num;
  }

  /**
   * Decodes a sorting code back to an integer.
   *
   * @param string $string
   *   The alpha decimal value to convert
   *
   * @return int
   *   The integer value.
   *
   * @see \Drupal\Component\Utility\Number::intToAlphadecimal
   */
  public static function alphadecimalToInt($string = '00') {
    return (int) base_convert(substr($string, 1), 36, 10);
  }

}
