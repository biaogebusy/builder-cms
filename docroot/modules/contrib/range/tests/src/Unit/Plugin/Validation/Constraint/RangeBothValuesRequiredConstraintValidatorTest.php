<?php

namespace Drupal\Tests\range\Unit\Plugin\Validation\Constraint;

use Drupal\range\Plugin\Validation\Constraint\RangeBothValuesRequiredConstraint;
use Drupal\range\Plugin\Validation\Constraint\RangeBothValuesRequiredConstraintValidator;
use Drupal\range\RangeItemInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Tests the RangeBothValuesRequiredConstraintValidator validator.
 *
 * @coversDefaultClass \Drupal\range\Plugin\Validation\Constraint\RangeBothValuesRequiredConstraintValidator
 * @group range
 */
class RangeBothValuesRequiredConstraintValidatorTest extends UnitTestCase {

  /**
   * Tests the RangeBothValuesRequiredConstraintValidator::validate() method.
   *
   * @param array $value
   *   Range item.
   * @param bool $is_valid
   *   A boolean indicating if the combination is expected to be valid.
   *
   * @covers ::validate
   * @dataProvider providerValidate
   */
  public function testValidate(array $value, bool $is_valid): void {
    $item = $this->createMock(RangeItemInterface::class);
    $item->expects($this->any())
      ->method('getValue')
      ->willReturn($value);

    $context = $this->createMock(ExecutionContextInterface::class);
    $context->expects($is_valid ? $this->never() : $this->once())
      ->method('addViolation');

    $constraint = new RangeBothValuesRequiredConstraint();
    $validate = new RangeBothValuesRequiredConstraintValidator();
    $validate->initialize($context);
    $validate->validate($item, $constraint);
  }

  /**
   * Data provider for testValidate().
   *
   * @return array
   *   Nested arrays of values to check:
   *     - $value
   *     - $is_valid
   */
  public static function providerValidate() {
    return [
      ['value' => ['from' => '', 'to' => 10], 'is_valid' => FALSE],
      ['value' => ['from' => 10, 'to' => ''], 'is_valid' => FALSE],
      ['value' => ['from' => '', 'to' => ''], 'is_valid' => FALSE],
      ['value' => ['from' => NULL, 'to' => 10], 'is_valid' => FALSE],
      ['value' => ['from' => 10, 'to' => NULL], 'is_valid' => FALSE],
      ['value' => ['from' => NULL, 'to' => NULL], 'is_valid' => FALSE],
      ['value' => ['from' => 0, 'to' => 0], 'is_valid' => TRUE],
      ['value' => ['from' => 0.0, 'to' => 0.0], 'is_valid' => TRUE],
      ['value' => ['from' => 10, 'to' => 10], 'is_valid' => TRUE],
    ];
  }

  /**
   * @covers ::validate
   */
  public function testInvalidValueType() {
    $context = $this->createMock(ExecutionContextInterface::class);
    $constraint = new RangeBothValuesRequiredConstraint();
    $validate = new RangeBothValuesRequiredConstraintValidator();
    $validate->initialize($context);

    $this->expectException(UnexpectedTypeException::class);
    $validate->validate(new \stdClass(), $constraint);
  }

}
