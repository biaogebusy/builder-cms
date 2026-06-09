<?php

namespace Drupal\Tests\ai\Unit\OperationType\Chat\Tools;

use Drupal\ai\OperationType\Chat\Tools\ToolsFunctionInput;
use Drupal\ai\OperationType\Chat\Tools\ToolsInput;
use Drupal\ai\OperationType\Chat\Tools\ToolsPropertyInput;
use PHPUnit\Framework\TestCase;

/**
 * Tests that the input works.
 *
 * @group ai
 * @covers \Drupal\ai\OperationType\Chat\Tools\ToolsInput
 */
class ToolsInputTest extends TestCase {

  /**
   * Test the constructor.
   *
   * @covers \Drupal\ai\OperationType\Chat\Tools\ToolsInput::__construct
   * @covers \Drupal\ai\OperationType\Chat\Tools\ToolsInput::getFunctions
   * @covers \Drupal\ai\OperationType\Chat\Tools\ToolsInput::setFunction
   * @covers \Drupal\ai\OperationType\Chat\Tools\ToolsInput::getFunctionByName
   * @covers \Drupal\ai\OperationType\Chat\Tools\ToolsInput::removeFunction
   * @covers \Drupal\ai\OperationType\Chat\Tools\ToolsInput::renderToolsArray
   */
  public function testConstructor() {
    $property = new ToolsPropertyInput('test_property', [
      'description' => 'Test description',
      'type' => 'string',
    ]);
    $function = new ToolsFunctionInput('test_function', [
      'description' => 'Test description',
      'properties' => [$property],
    ]);
    $input = new ToolsInput([$function]);
    $this->assertEquals([$function->getName() => $function], $input->getFunctions());
    // Set a new function.
    $function2 = new ToolsFunctionInput('test_function2', [
      'description' => 'Test description',
      'properties' => [$property],
    ]);
    $input->setFunction($function2);
    $this->assertEquals([$function->getName() => $function, $function2->getName() => $function2], $input->getFunctions());
    // Get a function by name.
    $this->assertEquals($function, $input->getFunctionByName($function->getName()));
    $this->assertEquals($function2, $input->getFunctionByName($function2->getName()));
    // Remove a function.
    $input->removeFunction($function->getName());
    $this->assertEquals([$function2->getName() => $function2], $input->getFunctions());

    // Test renderToolsArray.
    $output = $input->renderToolsArray();
    $compare = [
      [
        'type' => 'function',
        'function' => [
          'name' => 'test_function2',
          'description' => 'Test description',
          'parameters' => [
            'type' => 'object',
            'properties' => [
              'test_property' => [
                'name' => 'test_property',
                'type' => 'string',
                'description' => 'Test description',
              ],
            ],
          ],
        ],
      ],
    ];
    $this->assertEquals($compare, $output);
  }

}
