<?php

namespace Drupal\commerce_price\Comparator;

use Drupal\commerce\ComparisonFailureBridge;
use Drupal\commerce_price\Price;
use SebastianBergmann\Comparator\Comparator;

/**
 * Provides a PHPUnit comparator for prices.
 */
class PriceComparator extends Comparator {

  /**
   * {@inheritdoc}
   */
  public function accepts($expected, $actual): bool {
    return $expected instanceof Price && $actual instanceof Price;
  }

  /**
   * {@inheritdoc}
   */
  public function assertEquals($expected, $actual, $delta = 0.0, $canonicalize = FALSE, $ignoreCase = FALSE): void {
    assert($expected instanceof Price);
    assert($actual instanceof Price);
    if (!$actual->equals($expected)) {
      throw ComparisonFailureBridge::create(
        $expected,
        $actual,
        (string) $expected,
        (string) $actual,
        FALSE,
        sprintf('Failed asserting that Price %s matches expected %s.', $actual, $expected)
      );
    }
  }

}
