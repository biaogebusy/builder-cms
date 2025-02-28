<?php

namespace Shaper\Tests\Validator;

use Shaper\Validator\AcceptValidator;
use PHPUnit\Framework\TestCase;

/**
 * @package Shaper
 *
 * @coversDefaultClass \Shaper\Validator\AcceptValidator
 */
class AcceptValidatorTest extends TestCase {

  /**
   * @covers ::isValid
   */
  public function testIsValid() {
    $sut = new AcceptValidator();
    $this->assertTrue($sut->isValid(NULL));
    $this->assertTrue($sut->isValid(TRUE));
    $this->assertTrue($sut->isValid(42));
  }
}
