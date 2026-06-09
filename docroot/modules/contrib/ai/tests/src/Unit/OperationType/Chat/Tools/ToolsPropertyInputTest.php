<?php

namespace Drupal\Tests\ai\Unit\OperationType\Chat\Tools;

use Drupal\ai\OperationType\Chat\Tools\ToolsPropertyInput;
use PHPUnit\Framework\TestCase;

/**
 * Tests that the input functions function works.
 *
 * @group ai
 * @covers \Drupal\ai\OperationType\Chat\Tools\ToolsPropertyInput
 */
class ToolsPropertyInputTest extends TestCase {

  /**
   * Test the constructor with just name.
   *
   * @group ai
   * @covers \Drupal\ai\OperationType\Chat\Tools\ToolsPropertyInput::__construct
   */
  public function testConstructorWithName() {
    $input = new ToolsPropertyInput('name');
    $this->assertEquals('name', $input->getName());
    $this->assertEquals('name', $input->getName());
  }

  /**
   * Test the constructor without a name.
   *
   * @group ai
   * @covers \Drupal\ai\OperationType\Chat\Tools\ToolsPropertyInput::__construct
   */
  public function testConstructorWithoutName() {
    $input = new ToolsPropertyInput();
    $this->assertEquals('', $input->getName());
    $this->assertEquals('', $input->getName());
  }

  /**
   * Test the constructor with a name and a working array description.
   *
   * @group ai
   * @covers \Drupal\ai\OperationType\Chat\Tools\ToolsPropertyInput::__construct
   */
  public function testConstructorWithNameAndDescription() {
    $params['description'] = 'description';
    $input = new ToolsPropertyInput('name', $params);
    $this->assertEquals('name', $input->getName());
    $this->assertEquals('description', $input->getDescription());
  }

  /**
   * Test the constructor with a name and working type.
   *
   * @group ai
   * @covers \Drupal\ai\OperationType\Chat\Tools\ToolsPropertyInput::__construct
   */
  public function testConstructorWithNameAndType() {
    $params['type'] = 'type';
    $input = new ToolsPropertyInput('name', $params);
    $this->assertEquals('name', $input->getName());
    $this->assertEquals('type', $input->getType());
  }

  /**
   * Test the constructor with all the working params.
   *
   * @group ai
   * @covers \Drupal\ai\OperationType\Chat\Tools\ToolsPropertyInput::__construct
   */
  public function testConstructorWithAllParams() {
    $subProperty = new ToolsPropertyInput('subProperty');
    $params = [
      'description' => 'description',
      'type' => 'type',
      'minimum' => 1,
      'maximum' => 2,
      'default' => 2,
      'minLength' => 1,
      'maxLength' => 3,
      'pattern' => 'pattern',
      'format' => 'format',
      'exampleValue' => 'exampleValue',
      'testingSpecial' => 'customValue',
      'properties' => [$subProperty],
    ];
    $input = new ToolsPropertyInput('name', $params);
    $this->assertEquals('name', $input->getName());
    $this->assertEquals('description', $input->getDescription());
    $this->assertEquals('type', $input->getType());
    $this->assertEquals(1, $input->getMinimum());
    $this->assertEquals(2, $input->getMaximum());
    $this->assertEquals(2, $input->getDefault());
    $this->assertEquals(1, $input->getMinLength());
    $this->assertEquals(3, $input->getMaxLength());
    $this->assertEquals('pattern', $input->getPattern());
    $this->assertEquals('format', $input->getFormat());
    $this->assertEquals('exampleValue', $input->getExampleValue());
    $this->assertEquals('customValue', $input->getCustomValues()['testingSpecial']);
    $this->assertEquals($subProperty, $input->getProperties()[$subProperty->getName()]);
  }

  /**
   * Test the same with set methods.
   *
   * @group ai
   * @covers \Drupal\ai\OperationType\Chat\Tools\ToolsPropertyInput::__construct
   */
  public function testAllSetMethods() {
    $input = new ToolsPropertyInput();
    $input->setName('name');
    $input->setDescription('description');
    $input->setType('type');
    $input->setMinimum(1);
    $input->setMaximum(2);
    $input->setDefault(2);
    $input->setMinLength(1);
    $input->setMaxLength(3);
    $input->setPattern('pattern');
    $input->setFormat('format');
    $input->setExampleValue('exampleValue');
    $input->setCustomValue('testingSpecial', 'customValue');
    $subProperty = new ToolsPropertyInput('subProperty');
    $input->setProperties([$subProperty]);
    $this->assertEquals('name', $input->getName());
    $this->assertEquals('description', $input->getDescription());
    $this->assertEquals('type', $input->getType());
    $this->assertEquals(1, $input->getMinimum());
    $this->assertEquals(2, $input->getMaximum());
    $this->assertEquals(2, $input->getDefault());
    $this->assertEquals(1, $input->getMinLength());
    $this->assertEquals(3, $input->getMaxLength());
    $this->assertEquals('pattern', $input->getPattern());
    $this->assertEquals('format', $input->getFormat());
    $this->assertEquals('exampleValue', $input->getExampleValue());
    $this->assertEquals('customValue', $input->getCustomValues()['testingSpecial']);
    $this->assertEquals($subProperty, $input->getProperties()[$subProperty->getName()]);
  }

  /**
   * Test with different working and faulty function names.
   *
   * @group ai
   * @covers \Drupal\ai\OperationType\Chat\Tools\ToolsPropertyInput::setName
   */
  public function testDifferentNames() {
    $input = new ToolsPropertyInput();
    foreach ([
      'name',
      'name_tests',
      'nameTests',
      'MyFunction',
      'My_FunCTion',
    ] as $test) {
      $input->setName($test);
      $this->assertEquals($test, $input->getName());
    }

    // Will throw invalid argument exception.
    foreach ([
      'My-Function',
      'My Function',
      'My+Function',
      'My*Function',
      'My(Function',
      '',
    ] as $test) {
      try {
        $input->setName($test);
      }
      catch (\InvalidArgumentException $e) {
        $this->assertInstanceOf(\InvalidArgumentException::class, $e);
      }
    }
  }

  /**
   * Try to insert correct and faulty types.
   *
   * @group ai
   * @covers \Drupal\ai\OperationType\Chat\Tools\ToolsPropertyInput::setType
   */
  public function testDifferentTypes() {
    $input = new ToolsPropertyInput();
    foreach ([
      'string_test' => [
        'input' => 'string',
        'expected_output' => 'string',
      ],
      'something_custom_is_ok' => [
        'input' => 'something_custom_is_ok',
        'expected_output' => 'something_custom_is_ok',
      ],
      'simple_array' => [
        'input' => ['string', 'number'],
        'expected_output' => ['anyOf' => ['string', 'number']],
      ],
      'any_of' => [
        'input' => ['anyOf' => ['string', 'number']],
        'expected_output' => ['anyOf' => ['string', 'number']],
      ],
      'all_of' => [
        'input' => ['allOf' => ['string', 'number']],
        'expected_output' => ['allOf' => ['string', 'number']],
      ],
      'one_of' => [
        'input' => ['oneOf' => ['string', 'number']],
        'expected_output' => ['oneOf' => ['string', 'number']],
      ],
      'not' => [
        'input' => ['not' => ['string', 'number']],
        'expected_output' => ['not' => ['string', 'number']],
      ],
    ] as $test) {
      $input->setType($test['input']);
      $this->assertEquals($test['expected_output'], $input->getType());
    }

    // Will throw invalid argument exception.
    foreach ([
      'super string',
      [
        'anyOf' => [
          'test' => ['array'],
        ],
      ],
      [
        'customKey' => ['string'],
      ],
    ] as $test) {
      try {
        $input->setType($test);
      }
      catch (\InvalidArgumentException $e) {
        $this->assertInstanceOf(\InvalidArgumentException::class, $e);
      }
    }
  }

  /**
   * Test setting enum.
   *
   * @group ai
   * @covers \Drupal\ai\OperationType\Chat\Tools\ToolsPropertyInput::setEnum
   */
  public function testEnum() {
    $input = new ToolsPropertyInput();
    // Normal array.
    $input->setEnum(['test', 'test2']);
    $this->assertEquals(['test', 'test2'], $input->getEnum());
    // Associative array.
    $input->setEnum(['test' => 'test2']);
    $this->assertEquals(['test' => 'test2'], $input->getEnum());

    // Will throw invalid argument exception.
    try {
      // Complex array.
      $input->setEnum(['test', ['test2']]);
    }
    catch (\InvalidArgumentException $e) {
      $this->assertInstanceOf(\InvalidArgumentException::class, $e);
    }
  }

  /**
   * Test settings properties.
   *
   * @group ai
   * @covers \Drupal\ai\OperationType\Chat\Tools\ToolsPropertyInput::setProperties
   */
  public function testProperties() {
    $input = new ToolsPropertyInput();
    $subProperty = new ToolsPropertyInput('subProperty');
    $input->setProperties([$subProperty]);
    $this->assertEquals([$subProperty->getName() => $subProperty], $input->getProperties());
    // It shouldn't add a new one, since its the same name.
    $input->setProperties([$subProperty]);
    $this->assertEquals([$subProperty->getName() => $subProperty], $input->getProperties());
  }

  /**
   * Render from array.
   *
   * @group ai
   * @covers \Drupal\ai\OperationType\Chat\Tools\ToolsPropertyInput::renderPropertyArray
   */
  public function testRenderFromArray() {
    $input = new ToolsPropertyInput();
    $input->setName('name');
    $input->setDescription('description');
    $input->setType('type');
    $input->setMinimum(1);
    $input->setMaximum(2);
    $input->setDefault(2);
    $input->setMinLength(1);
    $input->setMaxLength(3);
    $input->setPattern('pattern');
    $input->setFormat('format');
    $input->setExampleValue('exampleValue');
    $input->setCustomValue('testingSpecial', 'customValue');
    $output = $input->renderPropertyArray();
    $realArray = [
      'name' => 'name',
      'description' => 'description',
      'type' => 'type',
      'minimum' => 1,
      'maximum' => 2,
      'default' => 2,
      'minLength' => 1,
      'maxLength' => 3,
      'pattern' => 'pattern',
      'format' => 'format',
      'exampleValue' => 'exampleValue',
      'testingSpecial' => 'customValue',
    ];
    $this->assertEquals($realArray, $output);
  }

}
