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
   * @param \Drupal\range\RangeItemInterface $value
   *   Range item.
   * @param bool $valid
   *   A boolean indicating if the combination is expected to be valid.
   *
   * @covers ::validate
   * @dataProvider providerValidate
   */
  public function testValidate(RangeItemInterface $value, $valid) {
    $context = $this->createMock(ExecutionContextInterface::class);

    if ($valid) {
      $context->expects($this->never())
        ->method('addViolation');
    }
    else {
      $context->expects($this->once())
        ->method('addViolation');
    }

    $constraint = new RangeBothValuesRequiredConstraint();

    $validate = new RangeBothValuesRequiredConstraintValidator();
    $validate->initialize($context);
    $validate->validate($value, $constraint);
  }

  /**
   * Data provider for testValidate().
   *
   * @return array
   *   Nested arrays of values to check:
   *     - $item
   *     - $valid
   */
  public function providerValidate() {
    $data = [];

    $cases = [
      ['range' => ['from' => '', 'to' => 10], 'valid' => FALSE],
      ['range' => ['from' => 10, 'to' => ''], 'valid' => FALSE],
      ['range' => ['from' => '', 'to' => ''], 'valid' => FALSE],
      ['range' => ['from' => NULL, 'to' => 10], 'valid' => FALSE],
      ['range' => ['from' => 10, 'to' => NULL], 'valid' => FALSE],
      ['range' => ['from' => NULL, 'to' => NULL], 'valid' => FALSE],
      ['range' => ['from' => 0, 'to' => 0], 'valid' => TRUE],
      ['range' => ['from' => 0.0, 'to' => 0.0], 'valid' => TRUE],
      ['range' => ['from' => 10, 'to' => 10], 'valid' => TRUE],
    ];

    foreach ($cases as $case) {
      $item = $this->createMock('Drupal\range\RangeItemInterface');
      $item->expects($this->any())
        ->method('getValue')
        ->willReturn($case['range']);
      $data[] = [$item, $case['valid']];
    }

    return $data;
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
