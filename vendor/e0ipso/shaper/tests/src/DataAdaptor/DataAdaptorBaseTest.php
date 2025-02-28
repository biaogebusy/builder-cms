<?php

namespace Shaper\Tests\DataAdaptor;

use PHPUnit\Framework\TestCase;
use Shaper\Util\Context;

/**
 * @package Shaper
 *
 * @coversDefaultClass \Shaper\DataAdaptor\DataAdaptorBase
 */
class DataAdaptorBaseTest extends TestCase {

  /**
   * @var \Shaper\Util\Context
   */
  protected $context;

  protected function setUp(): void {
    parent::setUp();
    $this->context = new Context();
    $this->context['key'] = 'lorem';
  }

  /**
   * @covers ::transform
   * @covers ::conformsToExpectedInputShape
   * @covers ::conformsToInternalShape
   */
  public function testtransform() {
    $sut = new DataAdaptorFake();
    $data = new \stdClass();
    $data->lorem = 'caramba!';
    $actual = $sut->transform($data, $this->context);
    $this->assertSame('caramba!', $actual);
  }

  /**
   * @covers ::transform
   */
  public function testtransformErrorInput() {
    $sut = new DataAdaptorFake2();
    $data = new \stdClass();
    $data->lorem = 'caramba!';
    $this->expectException(\TypeError::class);
    $sut->transform($data, $this->context);
  }

  /**
   * @covers ::transform
   */
  public function testtransformErrorOutput() {
    $sut = new DataAdaptorFake3();
    $data = new \stdClass();
    $data->lorem = 'caramba!';
    $this->expectException(\TypeError::class);
    $sut->transform($data);
  }

  /**
   * @covers ::undoTransform
   * @covers ::conformsToInternalShape
   * @covers ::conformsToOutputShape
   */
  public function testundoTransform() {
    $sut = new DataAdaptorFake();
    $actual = $sut->undoTransform('caramba!', $this->context);
    $expected = new \stdClass();
    $expected->lorem = 'caramba!';
    $expected->bar = 'default';
    $this->assertEquals($expected, $actual);
  }

  /**
   * @covers ::undoTransform
   */
  public function testundoTransformErrorInput() {
    $sut = new DataAdaptorFake3();
    $expected = new \stdClass();
    $expected->lorem = 'caramba!';
    $expected->bar = 'default';
    $this->expectException(\TypeError::class);
    $sut->undoTransform('caramba!', $this->context);
  }

  /**
   * @covers ::undoTransform
   */
  public function testundoTransformErrorOutput() {
    $sut = new DataAdaptorFake4();
    $expected = new \stdClass();
    $expected->lorem = 'caramba!';
    $expected->bar = 'default';
    $this->expectException(\TypeError::class);
    $sut->undoTransform('caramba!');
  }

}
