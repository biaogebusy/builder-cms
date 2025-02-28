<?php

namespace Shaper\Tests\Transformation;

use JsonSchema\Validator;
use PHPUnit\Framework\TestCase;
use Shaper\Transformation\TransformationBase;
use Shaper\Transformation\TransformationsQueue;
use Shaper\Util\Context;
use Shaper\Validator\AcceptValidator;
use Shaper\Validator\JsonSchemaValidator;

class TransformationFake2 extends TransformationBase {
  public function getInputValidator() {
    return new JsonSchemaValidator(['type' => 'number'], new Validator());
  }
  public function getOutputValidator() {
    $schema = ['type' => 'array', 'items' => ['type' => 'number']];
    return new JsonSchemaValidator($schema, new Validator());
  }
  protected function doTransform($data, Context $context) {
    return [$data];
  }
}

/**
 * @package Shaper
 *
 * @coversDefaultClass \Shaper\Transformation\TransformationsQueue
 */
class TransformationsQueueTest extends TestCase {

  /**
   * @var \Shaper\Transformation\TransformationsQueue
   */
  protected $sut;

  /**
   * @var \Shaper\Util\Context
   */
  protected $context;

  protected function setUp(): void {
    parent::setUp();
    $this->sut = new TransformationsQueue();
    $this->sut->push(new TransformationFake());
    $this->sut->push(new TransformationFake2());
    $this->context = new Context();
  }


  /**
   * @covers ::transform
   */
  public function testTransform() {
    $actual = $this->sut->transform('foo', $this->context);
    $this->assertEquals([42], $actual);
    $actual = $this->sut->transform('foo');
    $this->assertEquals([42], $actual);
  }

  /**
   * @covers ::getInputValidator
   */
  public function testGetInputValidator() {
    $this->assertFalse($this->sut->conformsToExpectedInputShape([], $this->context));
  }

  /**
   * @covers ::getOutputValidator
   */
  public function testGetOutputValidator() {
    $this->assertFalse($this->sut->conformsToOutputShape(new \stdClass(), $this->context));
  }

}
