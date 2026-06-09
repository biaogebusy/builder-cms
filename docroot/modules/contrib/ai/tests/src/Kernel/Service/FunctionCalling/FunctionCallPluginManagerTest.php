<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Kernel\Service\FunctionCalling;

use Drupal\ai\OperationType\Chat\Tools\ToolsFunctionOutput;
use Drupal\ai_test\Plugin\AiFunctionCall\Calculator;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests for the FunctionCallPluginManagerTest class.
 *
 * @group ai
 */
final class FunctionCallPluginManagerTest extends KernelTestBase {

  /**
   * The function call plugin manager.
   *
   * @var \Drupal\ai\Plugin\AiFunctionCall\AiFunctionCallManager
   */
  protected $functionCallManager;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'user',
    'key',
    'ai',
    'system',
    'field',
    'link',
    'text',
    'ai_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->functionCallManager = $this->container->get('plugin.manager.ai.function_calls');

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
  }

  /**
   * Try to get all the definitions of the function call plugins.
   */
  public function testGetAllFunctionCallDefinitions(): void {
    $definitions = $this->functionCallManager->getDefinitions();
    $this->assertNotEmpty($definitions, 'Function call definitions are not empty.');
    // Should have two definitions: Calculator and Weather.
    $this->assertArrayHasKey('ai:calculator', $definitions, 'Calculator function call definition exists.');
    $this->assertArrayHasKey('ai:weather', $definitions, 'Weather function call definition exists.');
  }

  /**
   * Try the get executable definitions method.
   */
  public function testGetExecutableDefinitions(): void {
    $executable_definitions = $this->functionCallManager->getExecutableDefinitions();
    $this->assertNotEmpty($executable_definitions, 'Executable function call definitions are not empty.');
    // Should have two definitions: Calculator and Weather.
    $this->assertArrayHasKey('ai:calculator', $executable_definitions, 'Calculator executable function call definition exists.');
    $this->assertArrayHasKey('ai:weather', $executable_definitions, 'Weather executable function call definition exists.');
  }

  /**
   * Try so function exists method works.
   */
  public function testFunctionExists(): void {
    $this->assertTrue($this->functionCallManager->functionExists('calculator'), 'Function ai_calculator_add exists.');
    $this->assertFalse($this->functionCallManager->functionExists('ai_non_existent_function'), 'Function ai_non_existent_function does not exist.');
  }

  /**
   * Try to convert tool response to function call response.
   */
  public function testConvertToolResponseToFunctionCallResponse(): void {
    $input = $this->functionCallManager->createInstance('ai:calculator');
    $output = new ToolsFunctionOutput($input->normalize(), 'random', [
      'expression' => '5 + 10',
    ]);

    $function_call_response = $this->functionCallManager->convertToolResponseToObject($output);
    // It should be a real calculator instance.
    $this->assertInstanceOf(Calculator::class, $function_call_response, 'Function call response is an instance of Calculator.');
    $function_call_response->execute();
    // The response should be 15.
    $this->assertEquals(15, $function_call_response->getReadableOutput(), 'Function call response output is correct.');
  }

  /**
   * Create an instance from a function name.
   */
  public function testGetFunctionCallFromFunctionName(): void {
    $function_call = $this->functionCallManager->getFunctionCallFromFunctionName('calculator');
    $this->assertInstanceOf(Calculator::class, $function_call, 'Function call from function name is an instance of Calculator.');
  }

  /**
   * Try to get function call from class.
   */
  public function testGetFunctionCallFromClass(): void {
    $function_call = $this->functionCallManager->getFunctionCallFromClass(Calculator::class);
    $this->assertInstanceOf(Calculator::class, $function_call, 'Function call from class is an instance of Calculator.');
  }

}
