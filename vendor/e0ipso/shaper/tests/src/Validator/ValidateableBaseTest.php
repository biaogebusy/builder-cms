<?php

namespace Shaper\Tests\Validator;

use PHPUnit\Framework\TestCase;
use Shaper\Validator\ValidateableBase;

class FakeValidateable extends ValidateableBase {

  public function isValid($data) {
    return FALSE;
  }

  public function setErrors($value) {
    $this->errors = $value;
  }

}

/**
 * @package Shaper
 *
 * @coversDefaultClass \Shaper\Validator\ValidateableBase
 */
class ValidateableBaseTest extends TestCase {

  /**
   * @covers ::getErrors
   */
  public function testGetErrors() {
    $sut = new FakeValidateable();
    $sut->setErrors('foo');
    $this->assertSame('foo', $sut->getErrors());
  }

  /**
   * @covers ::resetErrors
   */
  public function testResetErrors() {
    $sut = new FakeValidateable();
    $sut->setErrors('foo');
    $sut->resetErrors();
    $this->assertEquals([], $sut->getErrors());
  }

}
