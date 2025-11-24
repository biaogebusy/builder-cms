<?php

namespace Drupal\Tests\range\Unit\Plugin\Validation\Constraint;

use Drupal\range\Plugin\Validation\Constraint\RangeFromGreaterToConstraint;
use Drupal\range\Plugin\Validation\Constraint\RangeFromGreaterToConstraintValidator;
use Drupal\range\RangeItemInterface;
use Drupal\Tests\UnitTestCase;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Tests the RangeFromGreaterToConstraintValidator validator.
 *
 * @coversDefaultClass \Drupal\range\Plugin\Validation\Constraint\RangeFromGreaterToConstraintValidator
 * @group range
 */
class RangeFromGreaterToConstraintValidatorTest extends UnitTestCase {

  /**
   * Tests the RangeFromGreaterToConstraintValidator::validate() method.
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

    $constraint = new RangeFromGreaterToConstraint();
    $validate = new RangeFromGreaterToConstraintValidator();
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
      ['value' => ['from' => 5, 'to' => 10], 'is_valid' => TRUE],
      ['value' => ['from' => 10, 'to' => 10], 'is_valid' => TRUE],
      ['value' => ['from' => 10, 'to' => 5], 'is_valid' => FALSE],
    ];
  }

  /**
   * @covers ::validate
   */
  public function testInvalidValueType() {
    $context = $this->createMock(ExecutionContextInterface::class);
    $constraint = new RangeFromGreaterToConstraint();
    $validate = new RangeFromGreaterToConstraintValidator();
    $validate->initialize($context);

    $this->expectException(UnexpectedTypeException::class);
    $validate->validate(new \stdClass(), $constraint);
  }

}
