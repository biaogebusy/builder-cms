<?php

namespace Shaper\Tests\Transformation;

use JsonSchema\Validator;
use PHPUnit\Framework\TestCase;
use Shaper\Transformation\TransformationBase;
use Shaper\Util\Context;
use Shaper\Validator\AcceptValidator;
use Shaper\Validator\JsonSchemaValidator;

/**
 * @package Shaper
 *
 * @coversDefaultClass \Shaper\Transformation\TransformationBase
 */
class TransformationBaseTest extends TestCase {

  /**
   * @covers ::transform
   * @covers ::getInputValidator
   * @covers ::getOutputValidator
   * @covers ::conformsToExpectedInputShape
   * @covers ::conformsToOutputShape
   */
  public function testTransform() {
    $sut = new TransformationFake();
    $actual = $sut->transform('foo');
    $this->assertSame(42, $actual);
    $this->expectException(\TypeError::class);
    $sut->transform([], new Context());
  }

  /**
   * @covers ::transform
   */
  public function testTransformBeforeError() {
    $sut = new TransformationFake();
    $this->expectException(\TypeError::class);
    $sut->transform([], new Context());
  }

  /**
   * @covers ::transform
   */
  public function testTransformAfterError() {
    $sut = new TransformationFail();
    $this->expectException(\TypeError::class);
    $sut->transform('foo', new Context());
  }

}
