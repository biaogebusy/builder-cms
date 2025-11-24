<?php

declare(strict_types=1);

namespace Drupal\Tests\image_effects\Unit;

use Drupal\image_effects\Plugin\ImageToolkit\Operation\ArgumentsTypeValidator;
use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Drupal\image_effects\Plugin\ImageToolkit\Operation\ArgumentsTypeValidator
 * @group image_effects
 */
class ArgumentsTypeValidatorTest extends TestCase {

  /**
   * Tests ::validate() for valid cases.
   *
   * @param array $arguments
   *   The arguments.
   * @param array $expected
   *   The expected result of validation.
   *
   * @dataProvider providerValidCases
   */
  public function testValidCases(array $arguments, array $expected): void {
    $this->assertSame($expected, ArgumentsTypeValidator::validate(self::argumentsDefinition(), $arguments));
  }

  /**
   * Data provider for valid test cases.
   *
   * This method returns a list of valid argument values that should pass the
   * validation process when passed to the ArgumentsTypeValidator::validate()
   * method.
   *
   * @return array
   *   An array of test cases where the keys are the arguments and the values
   *   are the expected results after validation.
   */
  public static function providerValidCases(): array {
    $object = new \stdClass();
    return [
      [['test_int' => 10], ['test_int' => 10]],
      [['test_int' => 10.0], ['test_int' => 10]],
      [['test_int' => '10'], ['test_int' => 10]],
      [['test_int' => '10.0'], ['test_int' => 10]],
      [['test_int_nullable' => 10], ['test_int_nullable' => 10]],
      [['test_int_nullable' => 10.0], ['test_int_nullable' => 10]],
      [['test_int_nullable' => '10'], ['test_int_nullable' => 10]],
      [['test_int_nullable' => '10.0'], ['test_int_nullable' => 10]],
      [['test_int_nullable' => NULL], ['test_int_nullable' => NULL]],
      [['test_float' => 10], ['test_float' => 10.0]],
      [['test_float' => 10.0], ['test_float' => 10.0]],
      [['test_float' => '10'], ['test_float' => 10.0]],
      [['test_float' => '10.0'], ['test_float' => 10.0]],
      [['test_string' => '10'], ['test_string' => '10']],
      [['test_string' => 10], ['test_string' => '10']],
      [['test_string' => '10.0'], ['test_string' => '10.0']],
      [['test_string' => 10.0], ['test_string' => '10']],
      [['test_string' => 10.1], ['test_string' => '10.1']],
      [['test_string' => 'foo'], ['test_string' => 'foo']],
      [['test_bool' => '1'], ['test_bool' => TRUE]],
      [['test_bool' => 1], ['test_bool' => TRUE]],
      [['test_bool' => TRUE], ['test_bool' => TRUE]],
      [['test_bool' => '0'], ['test_bool' => FALSE]],
      [['test_bool' => 0], ['test_bool' => FALSE]],
      [['test_bool' => FALSE], ['test_bool' => FALSE]],
      [['test_array' => ['foo']], ['test_array' => ['foo']]],
      [['test_object' => $object], ['test_object' => $object]],
    ];
  }

  /**
   * Tests ::validate() for invalid cases.
   *
   * @param array $arguments
   *   The argument value.
   * @param string $expected
   *   The expected exception message.
   *
   * @dataProvider providerInvalidCases
   */
  public function testInvalidCases(array $arguments, string $expected): void {
    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage($expected);
    ArgumentsTypeValidator::validate(self::argumentsDefinition(), $arguments);
  }

  /**
   * Data provider for invalid test cases.
   *
   * This provider returns a list of invalid argument values that should trigger
   * validation exceptions when passed to the ArgumentsTypeValidator::validate()
   * method.
   *
   * @return array
   *   An array of invalid argument sets with the expected exception messages.
   */
  public static function providerInvalidCases(): array {
    $object = new \stdClass();
    return [
      [['test_int' => 'foo'], 'Invalid value for argument \'test_int\', expected integer'],
      [['test_int' => [10]], 'Invalid value for argument \'test_int\', expected integer'],
      [['test_int' => $object], 'Object of class stdClass passed to \'test_int\', expected int'],
      [['test_int' => NULL], 'NULL passed to \'test_int\', expected int'],
      [['test_float' => 'foo'], 'Invalid value for argument \'test_float\', expected float'],
      [['test_float' => [10.0]], 'Invalid value for argument \'test_float\', expected float'],
      [['test_float' => $object], 'Object of class stdClass passed to \'test_float\', expected float'],
      [['test_float' => NULL], 'NULL passed to \'test_float\', expected float'],
      [['test_string' => ['10']], 'Invalid array value for argument \'test_string\', expected string'],
      [['test_string' => $object], 'Object of class stdClass passed to \'test_string\', expected string'],
      [['test_string' => NULL], 'NULL passed to \'test_string\', expected string'],
      [['test_bool' => NULL], 'NULL passed to \'test_bool\', expected bool'],
      [['test_array' => 'foo'], 'Invalid value for argument \'test_array\', expected array'],
      [['test_array' => $object], 'Object of class stdClass passed to \'test_array\', expected array'],
      [['test_array' => NULL], 'NULL passed to \'test_array\', expected array'],
      [['test_object' => 'foo'], 'Invalid value for argument \'test_object\', expected stdClass'],
      [['test_object' => [10]], 'Invalid value for argument \'test_object\', expected stdClass'],
      [['test_object' => NULL], 'NULL passed to \'test_object\', expected stdClass'],
    ];
  }

  /**
   * Provides the argument definitions for the validate method.
   *
   * This method returns an array of argument definitions that specify the
   * expected types and descriptions for each argument. It is used by the
   * ArgumentsTypeValidator to validate the arguments passed to it.
   *
   * @return array
   *   An associative array of argument definitions.
   */
  private static function argumentsDefinition(): array {
    return [
      'test_int' => [
        'description' => 'An integer argument.',
        'type' => 'int',
      ],
      'test_int_nullable' => [
        'description' => 'A nullable integer argument.',
        'type' => '?int',
      ],
      'test_float' => [
        'description' => 'A float argument.',
        'type' => 'float',
      ],
      'test_string' => [
        'description' => 'A string argument.',
        'type' => 'string',
      ],
      'test_bool' => [
        'description' => 'A bool argument.',
        'type' => 'bool',
      ],
      'test_array' => [
        'description' => 'An array argument.',
        'type' => 'array',
      ],
      'test_object' => [
        'description' => 'An array argument.',
        'type' => \stdClass::class,
      ],
    ];
  }

  /**
   * Tests the ::validate() method when a required argument type is missing.
   *
   * This test checks that a \RuntimeException is thrown when the argument
   * definition is missing a 'type' key, which is required for validation.
   * It ensures that the validator handles such cases correctly.
   */
  public function testMissingType(): void {
    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Undefined type for argument test_int');
    ArgumentsTypeValidator::validate([
      'test_int' => [
        'description' => 'An integer argument.',
      ],
      'test_float' => [
        'description' => 'A float argument.',
        'type' => 'float',
      ],
    ], ['test_int' => 'foo']);
  }

}
