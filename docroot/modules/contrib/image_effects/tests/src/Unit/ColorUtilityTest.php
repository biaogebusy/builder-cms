<?php

namespace Drupal\Tests\image_effects\Unit;

use Drupal\image_effects\Component\ColorUtility;
use PHPUnit\Framework\TestCase;

/**
 * Tests the color utility helper methods.
 *
 * @coversDefaultClass \Drupal\image_effects\Component\ColorUtility
 *
 * @group image_effects
 */
class ColorUtilityTest extends TestCase {

  /**
   * @covers ::validateRgba
   *
   * @param bool $expected
   *   The expected result of validation.
   * @param string $value
   *   The hex color value.
   *
   * @dataProvider providerTestValidateRgba
   */
  public function testValidateRgba(bool $expected, string $value): void {
    $this->assertSame($expected, ColorUtility::validateRgba($value));
  }

  /**
   * Provides data for testValidateRgba().
   */
  public function providerTestValidateRgba(): array {
    return [
      // Tests length.
      [FALSE, ''],
      [FALSE, '#'],
      [FALSE, '1'],
      [FALSE, '#1'],
      [FALSE, '12'],
      [FALSE, '#12'],
      [FALSE, '123'],
      [FALSE, '#123'],
      [FALSE, '1234'],
      [FALSE, '#1234'],
      [FALSE, '12345'],
      [FALSE, '#12345'],
      [FALSE, '123456'],
      [FALSE, '#123456'],
      [FALSE, '1234567'],
      [FALSE, '#1234567'],
      [FALSE, '123456FF'],
      [TRUE, '#123456FF'],
      [FALSE, '123456FFA'],
      [FALSE, '#123456FFA'],
      // Tests valid hex value.
      [FALSE, 'abcdef78'],
      [FALSE, 'ABCDEF78'],
      [FALSE, 'A0F1B1AA'],
      [TRUE, '#abcdef78'],
      [TRUE, '#ABCDEF78'],
      [TRUE, '#A0F1B1AA'],
      [FALSE, 'WWW'],
      [FALSE, '#123##'],
      [FALSE, '@a0055'],
      // Tests multiple hash prefix.
      [FALSE, '###F00F0011'],
      // Tests spaces.
      [FALSE, ' #12345678'],
      [FALSE, '12345678 '],
      [FALSE, '#12 345678'],
    ];
  }

}
