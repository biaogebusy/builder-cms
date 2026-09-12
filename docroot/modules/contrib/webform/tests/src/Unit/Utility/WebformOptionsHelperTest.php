<?php

namespace Drupal\Tests\webform\Unit\Utility;

use Drupal\Tests\UnitTestCase;
use Drupal\webform\Utility\WebformOptionsHelper;

/**
 * Tests webform options utility.
 *
 * @group webform
 *
 * @coversDefaultClass \Drupal\webform\Utility\WebformOptionsHelper
 */
class WebformOptionsHelperTest extends UnitTestCase {

  /**
   * Tests WebformOptionsHelper::hasOption().
   *
   * @param string $value
   *   The value to run through WebformOptionsHelper::hasOption().
   * @param array $options
   *   The array to run through WebformOptionsHelper::hasOption().
   * @param string $expected
   *   The expected result from calling the function.
   *
   * @see WebformOptionsHelper::hasOption()
   *
   * @dataProvider providerHasOption
   */
  public function testHasOption($value, array $options, $expected): void {
    $result = WebformOptionsHelper::hasOption($value, $options);
    $this->assertEquals($expected, $result);
  }

  /**
   * Data provider for testHasOption().
   *
   * @see testHasOption()
   */
  public static function providerHasOption() {
    $tests[] = ['value', ['value' => 'text'], TRUE];
    $tests[] = ['value', [], FALSE];
    $tests[] = [3, [1 => 'One', 2 => 'Two', 'optgroup' => [3 => 'Three']], TRUE];
    $tests[] = ['3', [1 => 'One', 2 => 'Two', 'optgroup' => [3 => 'Three']], TRUE];
    $tests[] = ['optgroup', [1 => 'One', 2 => 'Two', 'optgroup' => [3 => 'Three']], FALSE];
    return $tests;
  }

  /**
   * Tests WebformOptionsHelper::getOptionText().
   *
   * @param string $value
   *   The value to look for in the options.
   * @param array $options
   *   The array of options to search for the value.
   * @param bool $options_description
   *   Whether to remove the description from the text.
   * @param string $expected
   *   The expected result from calling the function.
   *
   * @see WebformOptionsHelper::getOptionText()
   *
   * @dataProvider providerGetOptionText
   */
  public function testGetOptionText($value, array $options, $options_description, $expected): void {
    $result = WebformOptionsHelper::getOptionText($value, $options, $options_description);
    $this->assertEquals($expected, $result);
  }

  /**
   * Data provider for testGetOptionText().
   *
   * @see testGetOptionText()
   */
  public static function providerGetOptionText() {
    $tests = [];

    // Basic test with a matching value.
    $tests[] = [
      'value',
      ['value' => 'This is the text'],
      FALSE,
      'This is the text',
    ];

    // Test with nested options.
    $tests[] = [
      3,
      [1 => 'One', 2 => 'Two', 'group' => [3 => 'Three']],
      FALSE,
      'Three',
    ];

    // Test with description included.
    $tests[] = [
      'with_description',
      ['with_description' => 'Option Text -- Description'],
      FALSE,
      'Option Text -- Description',
    ];

    // Test with description removed.
    $tests[] = [
      'with_description',
      ['with_description' => 'Option Text -- Description'],
      TRUE,
      'Option Text',
    ];

    // Test with non-matching value, returning input value.
    $tests[] = [
      'nonexistent_value',
      ['value1' => 'Text1', 'value2' => 'Text2'],
      FALSE,
      'nonexistent_value',
    ];

    // Test with nested options where the value does not exist.
    $tests[] = [
      99,
      [1 => 'One', 'group' => [2 => 'Two', 3 => 'Three']],
      FALSE,
      99,
    ];

    // Test with description delimiter but no description part.
    $tests[] = [
      'value_delimiter',
      ['value_delimiter' => 'Option Text -- '],
      TRUE,
      'Option Text',
    ];

    return $tests;
  }

  /**
   * Tests WebformOptionsHelper::getOptionsText().
   *
   * @param array $values
   *   The array to run through WebformOptionsHelper::getOptionsText().
   * @param array $options
   *   The array to run through WebformOptionsHelper::getOptionsText().
   * @param string $expected
   *   The expected result from calling the function.
   *
   * @see WebformOptionsHelper::getOptionsText()
   *
   * @dataProvider providerGetOptionsText
   */
  public function testGetOptionsText(array $values, array $options, $expected): void {
    $result = WebformOptionsHelper::getOptionsText($values, $options);
    $this->assertEquals($expected, $result);
  }

  /**
   * Data provider for testGetOptionsText().
   *
   * @see testGetOptionsText()
   */
  public static function providerGetOptionsText() {
    $tests[] = [['value'], ['value' => 'text'], ['text']];
    $tests[] = [[1, 3], [1 => 'One', 2 => 'Two', 'optgroup' => [3 => 'Three']], ['One', 'Three']];
    $tests[] = [[2], ['optgroup1' => [1 => 'One'], 'optgroup2' => [2 => 'Two']], ['Two']];
    return $tests;
  }

  /**
   * Tests WebformOptionsHelper::convertOptionsToString().
   *
   * @param array $options
   *   The array to run through WebformOptionsHelper::range().
   * @param string $expected
   *   The expected result from calling the function.
   *
   * @see WebformOptionsHelper::convertOptionsToString()
   *
   * @dataProvider providerConvertOptionsToString
   */
  public function testConvertOptionsToString(array $options, $expected): void {
    $result = WebformOptionsHelper::convertOptionsToString($options);
    $this->assertEquals($expected, $result);
  }

  /**
   * Data provider for testConvertOptionsToString().
   *
   * @see testConvertOptionsToString()
   */
  public static function providerConvertOptionsToString() {
    $tests[] = [[99 => 99], ['99' => 99]];
    $tests[] = [[TRUE => 99], ['1' => 99]];
    return $tests;
  }

  /**
   * Tests WebformOptionsHelper::range().
   *
   * @param array $element
   *   The array to run through WebformOptionsHelper::range().
   * @param string $expected
   *   The expected result from calling the function.
   *
   * @see WebformOptionsHelper::range()
   *
   * @dataProvider providerRange
   */
  public function testRange(array $element, $expected): void {
    $element += [
      '#min' => 1,
      '#max' => 100,
      '#step' => 1,
      '#pad_length' => NULL,
      '#pad_str' => 0,
    ];

    $result = WebformOptionsHelper::range(
      $element['#min'],
      $element['#max'],
      $element['#step'],
      $element['#pad_length'],
      $element['#pad_str']
    );
    $this->assertEquals($expected, $result);
  }

  /**
   * Data provider for testRange().
   *
   * @see testRange()
   */
  public static function providerRange() {
    $tests[] = [['#min' => 1, '#max' => 3], [1 => 1, 2 => 2, 3 => 3]];
    $tests[] = [['#min' => 0, '#max' => 6, '#step' => 2], [0 => 0, 2 => 2, 4 => 4, 6 => 6]];
    $tests[] = [['#min' => 'A', '#max' => 'C'], ['A' => 'A', 'B' => 'B', 'C' => 'C']];
    $tests[] = [['#min' => 'a', '#max' => 'c'], ['a' => 'a', 'b' => 'b', 'c' => 'c']];
    $tests[] = [['#min' => 1, '#max' => 3, '#step' => 1, '#pad_length' => 2, '#pad_str' => 0], ['01' => '01', '02' => '02', '03' => '03']];
    return $tests;
  }

  /**
   * Tests WebformOptionsHelper::encodeConfig().
   *
   * @param array $options
   *   The options array to run through WebformOptionsHelper::encodeConfig().
   * @param string $expected
   *   The expected result from calling the function.
   *
   * @see WebformOptionsHelper::encodeConfig()
   *
   * @dataProvider providerEncodeConfig
   */
  public function testEncodeConfig(array $options, $expected): void {
    $result = WebformOptionsHelper::encodeConfig($options);
    $this->assertEquals($expected, $result);
  }

  /**
   * Data provider for testEncodeConfig().
   *
   * @see testEncodeConfig()
   */
  public static function providerEncodeConfig() {
    $tests[] = [
      ['one' => 1, 'two' => 2, 'with.period' => 'with period'],
      [
        ['value' => 'one', 'text' => 1],
        ['value' => 'two', 'text' => 2],
        ['value' => 'with.period', 'text' => 'with period'],
      ],
    ];
    return $tests;
  }

  /**
   * Tests WebformOptionsHelper::decodeConfig().
   *
   * @param array $options
   *   The options array to run through WebformOptionsHelper::decodeConfig().
   * @param string $expected
   *   The expected result from calling the function.
   *
   * @see WebformOptionsHelper::decodeConfig()
   *
   * @dataProvider providerDecodeConfig
   */
  public function testDecodeConfig(array $options, $expected): void {
    $result = WebformOptionsHelper::decodeConfig($options);
    $this->assertEquals($expected, $result);
  }

  /**
   * Data provider for testDecodeConfig().
   *
   * @see testDecodeConfig()
   */
  public static function providerDecodeConfig() {
    $tests[] = [
      [
        ['value' => 'one', 'text' => 1],
        ['value' => 'two', 'text' => 2],
        ['value' => 'with.period', 'text' => 'with period'],
      ],
      ['one' => 1, 'two' => 2, 'with.period' => 'with period'],
    ];
    return $tests;
  }

}
