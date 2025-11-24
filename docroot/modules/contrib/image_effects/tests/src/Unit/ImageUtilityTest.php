<?php

declare(strict_types=1);

namespace Drupal\Tests\image_effects\Unit;

use Drupal\image_effects\Component\ImageUtility;
use PHPUnit\Framework\TestCase;

/**
 * Tests the image utility helper methods.
 *
 * @coversDefaultClass \Drupal\image_effects\Component\ImageUtility
 *
 * @group image_effects
 */
class ImageUtilityTest extends TestCase {

  /**
   * Data provider for testPercentFilter.
   */
  public static function percentFilterProvider(): array {
    return [
      [50, 400, 50],
      ['50', 400, 50],
      ['50%', 400, 200],
      [50, NULL, 50],
      ['50', NULL, 50],
      ['50%', NULL, NULL],
      [NULL, 100, NULL],
      [NULL, NULL, NULL],
      ['10%', 400, 40],
      ['100%', 400, 400],
      ['150%', 400, 600],
    ];
  }

  /**
   * @covers ::percentFilter
   * @dataProvider percentFilterProvider
   */
  public function testPercentFilter(int|string|NULL $length_specification, ?int $current_length, ?int $expected_result): void {
    $this->assertSame($expected_result, ImageUtility::percentFilter($length_specification, $current_length));
  }

  /**
   * Data provider for testResizeDimensions.
   */
  public static function resizeDimensionsProvider(): array {
    return [
      // Square = FALSE.
      [NULL, 100, 50, 25, FALSE, 50, 25],
      [200, NULL, 50, 25, FALSE, 50, 25],
      [NULL, NULL, 50, 25, FALSE, 50, 25],
      [200, 100, 50, 25, FALSE, 50, 25],
      [NULL, 100, '50%', '25%', FALSE, NULL, NULL],
      [200, NULL, '50%', '25%', FALSE, NULL, NULL],
      [NULL, NULL, '50%', '25%', FALSE, NULL, NULL],
      [200, 100, '50%', '25%', FALSE, 100, 25],
      [200, 100, '50%', '150%', FALSE, 100, 150],
      [200, 100, '150%', '10%', FALSE, 300, 10],
      [NULL, 100, '50', '25%', FALSE, 50, 25],
      [200, NULL, '50%', '25', FALSE, 100, 25],
      [200, 100, '50%', NULL, FALSE, 100, 50],
      [200, 100, NULL, '50%', FALSE, 100, 50],
      [40, 20, '100%', 0, FALSE, 40, 20],
      [40, 20, 0, '100%', FALSE, 40, 20],
      // Square = TRUE.
      [200, 100, 30, NULL, TRUE, 30, 30],
      [200, 100, NULL, 35, TRUE, 35, 35],
      [200, 100, '50%', NULL, TRUE, 100, 100],
      [200, 100, NULL, '50%', TRUE, 50, 50],
      [40, 20, '100%', 0, TRUE, 40, 40],
      [40, 20, 0, '100%', TRUE, 20, 20],
    ];
  }

  /**
   * @covers ::resizeDimensions
   * @dataProvider resizeDimensionsProvider
   */
  public function testResizeDimensions(?int $source_width, ?int $source_height, int|string|NULL $width_specification, int|string|NULL $height_specification, bool $square, ?int $expected_width, ?int $expected_height): void {
    $result = ImageUtility::resizeDimensions($source_width, $source_height, $width_specification, $height_specification, $square);
    $this->assertSame($expected_width, $result['width']);
    $this->assertSame($expected_height, $result['height']);
  }

  /**
   * @covers ::getKeywordOffset
   */
  public function testInvalidGetKeywordOffset(): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage('Invalid anchor \'foo\' provided to getKeywordOffset()');
    ImageUtility::getKeywordOffset('foo', 0, 0);
  }

  /**
   * @covers ::getKeywordOffset
   *
   * @dataProvider providerTestGetKeywordOffset
   */
  public function testGetKeywordOffset(array $input, int $expected): void {
    $this->assertSame($expected, ImageUtility::getKeywordOffset($input['anchor'], $input['current'], $input['new']));
  }

  /**
   * Provides data for testGetKeywordOffset().
   *
   * @return array
   *   Keyed array containing:
   *   - 'input' - Array which contains input for Image::getKeywordOffset().
   *   - 'expected' - The expected output.
   *
   * @see testGetKeywordOffset()
   */
  public static function providerTestGetKeywordOffset(): array {
    // Define input / output datasets to test different branch conditions.
    $tests = [];

    // Left and top => return 0.
    $tests[] = [
      'input' => [
        'anchor' => 'left',
        'current' => 100,
        'new' => 20,
      ],
      'expected' => 0,
    ];
    $tests[] = [
      'input' => [
        'anchor' => 'top',
        'current' => 100,
        'new' => 20,
      ],
      'expected' => 0,
    ];

    // Right and bottom => return (current - new).
    $tests[] = [
      'input' => [
        'anchor' => 'right',
        'current' => 100,
        'new' => 20,
      ],
      'expected' => 80,
    ];
    $tests[] = [
      'input' => [
        'anchor' => 'bottom',
        'current' => 100,
        'new' => 30,
      ],
      'expected' => 70,
    ];

    // Center => return (current - new)/2.
    $tests[] = [
      'input' => [
        'anchor' => 'center',
        'current' => 100,
        'new' => 20,
      ],
      'expected' => 40,
    ];
    $tests[] = [
      'input' => [
        'anchor' => 'center',
        'current' => 100,
        'new' => 91,
      ],
      'expected' => 5,
    ];

    return $tests;
  }

}
