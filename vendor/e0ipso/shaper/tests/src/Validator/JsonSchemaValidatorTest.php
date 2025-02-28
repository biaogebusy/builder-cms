<?php

namespace Shaper\Tests\Validator;

use JsonSchema\Validator;
use PHPUnit\Framework\TestCase;
use Shaper\Validator\JsonSchemaValidator;

/**
 * @package Shaper
 *
 * @coversDefaultClass \Shaper\Validator\JsonSchemaValidator
 */
class JsonSchemaValidatorTest extends TestCase {

  /**
   * @covers ::__construct
   * @covers ::setValidator
   */
  public function test__construct() {
    $sut = new JsonSchemaValidator(NULL, new Validator());
    $this->assertInstanceOf(JsonSchemaValidator::class, $sut);
  }

  /**
   * @covers ::toJSON
   */
  public function testToJSON() {
    $sut = new JsonSchemaValidator(['type' => 'string'], new Validator());
    $this->assertEquals('{"type":"string"}', $sut->toJSON());
  }

  /**
   * @covers ::isValid
   * @covers ::setValidator
   */
  public function testIsValid() {
    $sut = new JsonSchemaValidator(['type' => 'number']);
    $sut->setValidator(new Validator());
    $this->assertTrue($sut->isValid(42));
    $this->assertFalse($sut->isValid('not true'));
    $this->assertSame('String value found, but a number is required', $sut->getErrors()[0]['message']);
  }

  /**
   * @covers ::isValid
   */
  public function testIsValidError() {
    $sut = new JsonSchemaValidator(['type' => 'number']);
    $this->expectException(\InvalidArgumentException::class);
    $this->assertTrue($sut->isValid(NULL));
  }

  /**
   * @covers ::__sleep
   * @covers ::__wakeup
   */
  public function testSerialize() {
    $sut = new JsonSchemaValidator(['type' => 'number'], new Validator());
    $serialized = serialize($sut);
    $sut = unserialize($serialized);
    $this->assertInstanceOf(JsonSchemaValidator::class, $sut);
  }

  /**
   * @covers ::getErrors
   */
  public function testGetErrors() {
    $sut = new JsonSchemaValidator(['type' => 'number']);
    $sut->setValidator(new Validator());
    $sut->isValid(NULL);
    $this->assertEquals([[
     'property' => '',
     'pointer' => '',
     'message' => 'NULL value found, but a number is required',
     'constraint' => 'type',
     'context' => 1,
    ]], $sut->getErrors());
    $sut = new JsonSchemaValidator(['type' => 'number']);
    $this->assertEquals([], $sut->getErrors());
  }

  /**
   * @covers ::resetErrors
   */
  public function testResetErrors() {
    $sut = new JsonSchemaValidator(['type' => 'number']);
    $sut->setValidator(new Validator());
    $sut->isValid(NULL);
    $this->assertNotEmpty($sut->getErrors()[0]);
    $sut->resetErrors();
    $this->assertEmpty($sut->getErrors());
  }

}
