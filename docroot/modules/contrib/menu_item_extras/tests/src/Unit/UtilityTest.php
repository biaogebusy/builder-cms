<?php

namespace Drupal\Tests\menu_item_extras\Unit;

use Drupal\menu_item_extras\Utility\Utility;
use Drupal\Tests\UnitTestCase;

/**
 * Class For test Utility.
 *
 * @package Drupal\Tests\menu_item_extras\Unit
 *
 * @group menu_item_extras
 */
class UtilityTest extends UnitTestCase {

  /**
   * Test string sanitizing.
   */
  public function testSanitizeMachineName() {
    $matrix = [
      'test_1_suggestion_' => 'test-1.suggestion ',
      'test__2__suggestion' => 'test -2- suggestion*',
      'test__3__suggestion' => 'tes!t_-_-_ @#  -  -3- =%---/ suggestion*',
    ];
    foreach ($matrix as $expected => $input) {
      $this->assertEquals(
        $expected,
        Utility::sanitizeMachineName($input),
        "\"{$input}\" sanitized not properly");
    }
  }

  /**
   * Test suggestion utility.
   */
  public function testSuggestion() {
    $this->assertEquals('one__two__three', Utility::suggestion('one', 'two', 'three'));
    $this->assertEquals('one', Utility::suggestion('one'));
  }

  /**
   * Check sanitizing of suggestions.
   */
  public function testSanitizedSuggestion() {
    $this->assertEquals(
      'one__two__three',
      Utility::suggestion(
        Utility::sanitizeMachineName('one!@#$!#'),
        Utility::sanitizeMachineName('t!@#$w!@#$@!o_'),
        Utility::sanitizeMachineName('___three')
      )
    );
  }

}
