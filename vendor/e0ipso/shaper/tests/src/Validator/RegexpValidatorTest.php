<?php

namespace Shaper\Tests\Validator;

use Shaper\Validator\RegexpValidator;
use PHPUnit\Framework\TestCase;

/**
 * @package Shaper
 *
 * @coversDefaultClass \Shaper\Validator\RegexpValidator
 */
class RegexpValidatorTest extends TestCase {

  /**
   * @covers ::isValid
   * @covers ::__construct
   * @dataProvider providerIdValid
   */
  public function testIsValid($data, $expected) {
    $sut = new RegexpValidator('^d.*e$');
    $this->assertSame($expected, $sut->isValid($data));
    if (!$expected) {
      $message = sprintf(
        'String "%s" does not match regular expression /^d.*e$/ as expected.',
        $data
      );
      $this->assertSame($message, $sut->getErrors()[0]);
    }
  }

  /**
   * Data provider for testIsValid.
   *
   * @return array
   */
  public function providerIdValid() {
    return [
      ['de', TRUE],
      ['d123/\/@e', TRUE],
      ['42', FALSE],
      ['d123@', FALSE],
    ];
  }
}
