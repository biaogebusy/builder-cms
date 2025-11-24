<?php

namespace Drupal\commerce;

use SebastianBergmann\Comparator\ComparisonFailure;

/**
 * Create a bridge class for SebastianBergmann\Comparator\ComparisonFailure.
 *
 * The constructor changed between the version of the library required by D10 vs
 * the one required by D11.
 * This is an attempt to support the 2 different versions at the same time.
 */
final class ComparisonFailureBridge {

  /**
   * Builds a ComparisonFailure object based on the Drupal core version.
   *
   * @param mixed $expected
   *   The expected value retrieved.
   * @param mixed $actual
   *   The actual value.
   * @param string $expectedAsString
   *   The expected value as a string.
   * @param string $actualAsString
   *   The actual value as a string.
   * @param bool $identical
   *   This isn't actually used, but the old signature requires it.
   * @param string $message
   *   A string which is prefixed on all returned lines in the difference.
   *
   * @return \SebastianBergmann\Comparator\ComparisonFailure
   *   An instantiated ComparisonFailure object.
   */
  public static function create(mixed $expected, mixed $actual, string $expectedAsString, string $actualAsString, bool $identical = FALSE, string $message = '') {
    if (version_compare(\Drupal::VERSION, '11', '>=')) {
      return new ComparisonFailure($expected, $actual, $expectedAsString, $actualAsString, $message);
    }
    else {
      return new ComparisonFailure($expected, $actual, $expectedAsString, $actualAsString, $identical, $message);
    }
  }

}
