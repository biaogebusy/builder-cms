<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Unit\Utility;

use Drupal\Tests\UnitTestCase;
use Drupal\ai\Utility\CastUtility;

/**
 * @coversDefaultClass \Drupal\ai\Utility\CastUtility
 * @group ai
 */
class CastUtilityTest extends UnitTestCase {

  /**
   * Tests casted values.
   *
   * @param string $type
   *   The parameter type.
   * @param mixed $value
   *   The passed value.
   * @param mixed $expected
   *   The value returned from casting.
   *
   * @dataProvider typeAndValueProvider
   *
   * @return void
   *   Nothing.
   */
  public function testTypeCasting(string $type, mixed $value, $expected): void {
    $this->assertSame($expected, CastUtility::typeCast($type, $value));
  }

  /**
   * Provides types, values and expected values for testing.
   *
   * @return array
   *   Types, values and expected values.
   */
  public static function typeAndValueProvider(): array {
    return [
      ["int", "1", 1],
      ["integer", "1", 1],
      ["float", "1", 1.0],
      ["bool", 1, TRUE],
      ["bool", "1", FALSE],
      ["bool", "TRUE", TRUE],
      ["boolean", 1, TRUE],
      ["boolean", "1", FALSE],
      ["boolean", "TRUE", TRUE],
      ["string", 1, "1"],
      ["array", 1, [1]],
    ];
  }

}
