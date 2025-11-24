<?php

declare(strict_types=1);

namespace Drupal\Tests\image_effects\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\image_effects\Component\TextUtility;

/**
 * Tests the UTF-8 character-based wrapper of the preg_match function.
 *
 * @group image_effects
 */
class TextUtilityTest extends UnitTestCase {

  /**
   * Performs the tests for the offset argument.
   */
  public function testOffsetArgument(): void {
    $matches = [];

    // Character 'п' is 2 bytes long and preg_match() would start from the
    // second 'п' character and not from the first 'z'.
    $result = TextUtility::unicodePregMatch('/п/u', 'ппzz', $matches, 0, 2);
    $this->assertEquals(0, $result, 'String was skipped using character-based offset.');

    // Again, character 'п' is 2 bytes long and we skip 1 character, so
    // preg_match() would fail, because the string with byte offset 1 is not a
    // valid UTF-8 string.
    $result = TextUtility::unicodePregMatch('/.*$/u', 'пzz', $matches, 0, 1);
    $this->assertTrue($result && $matches[0] === 'zz', 'String was matched using character-based offset.');
  }

  /**
   * Performs the tests for the captured offset.
   */
  public function testCapturedOffset(): void {
    $matches = [];

    // Character 'п' is 2 bytes long and non-unicode preg_match would return
    // 2 here.
    $result = TextUtility::unicodePregMatch('/z/u', 'пz', $matches, PREG_OFFSET_CAPTURE);
    $this->assertTrue($result && $matches[0][1] === 1, 'Returned offset is character-based.');
  }

}
