<?php

namespace Shaper\Tests\Validator;

use PHPUnit\Framework\TestCase;
use Shaper\Validator\InstanceofValidator;

/**
 * @package Shaper
 *
 * @coversDefaultClass \Shaper\Validator\InstanceofValidator
 */
class InstanceofValidatorTest extends TestCase {

  /**
   * @covers ::__construct
   */
  public function test__construct() {
    $sut = new InstanceofValidator(TestCase::class);
    $this->assertInstanceOf(InstanceofValidator::class, $sut);
  }

  /**
   * @covers ::__construct
   */
  public function test__constructError() {
    $this->expectException(\TypeError::class);
    new InstanceofValidator('IAmAFail');
  }

  /**
   * @covers ::isValid
   */
  public function testIsValid() {
    $sut = new InstanceofValidator(\stdClass::class);
    $this->assertTrue($sut->isValid(new \stdClass()));
    $this->assertFalse($sut->isValid(new \Exception()));
    $this->assertSame('"Exception" does not extend or implement "stdClass".', $sut->getErrors()[0]);
    $this->assertFalse($sut->isValid('42'));
    $this->assertSame('"42" does not extend or implement "stdClass".', $sut->getErrors()[0]);
  }

}
