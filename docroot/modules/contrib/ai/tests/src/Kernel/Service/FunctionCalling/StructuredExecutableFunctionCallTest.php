<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Kernel\Service\FunctionCalling;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ai\Service\FunctionCalling\StructuredExecutableFunctionCallInterface;
use Drupal\ai_test\Plugin\AiFunctionCall\Calculator;
use Drupal\ai_test\Plugin\AiFunctionCall\Weather;

/**
 * Tests for the StructuredExecutableFunctionCallTest class.
 *
 * @group ai
 */
final class StructuredExecutableFunctionCallTest extends KernelTestBase {

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
   * Test that the Weather function call works as expected and is structured.
   */
  public function testCalculatorFunctionCall(): void {
    $weather = $this->functionCallManager->createInstance('ai:weather');
    $this->assertInstanceOf(Weather::class, $weather, 'Weather function call plugin instance is created.');

    // Call the function with city London, country UK, and unit celsius.
    $weather->setContextValue('city', 'London');
    $weather->setContextValue('country', 'UK');
    $weather->setContextValue('unit', 'celsius');

    // Execute the function call.
    $weather->execute();

    // It should have a normal string output.
    $this->assertIsString($weather->getReadableOutput(), 'Weather function call has a string output.');
    $this->assertEquals('15°C', $weather->getReadableOutput(), 'Weather function call returns the expected temperature for London in Celsius.');

    // It should have a structured output.
    $structured_output = $weather->getStructuredOutput();
    $this->assertIsArray($structured_output, 'Weather function call has a structured output.');
    $this->assertArrayHasKey('city', $structured_output, 'Weather function call structured output has city key.');
    $this->assertArrayHasKey('country', $structured_output, 'Weather function call structured output has country key.');
    $this->assertArrayHasKey('unit', $structured_output, 'Weather function call structured output has unit key.');
    $this->assertArrayHasKey('temperature', $structured_output, 'Weather function call structured output has temperature key.');
    $this->assertEquals('London', $structured_output['city'], 'Weather function call structured output has the correct city.');
    $this->assertEquals('UK', $structured_output['country'], 'Weather function call structured output has the correct country.');
    $this->assertEquals('celsius', $structured_output['unit'], 'Weather function call structured output has the correct unit.');
    $this->assertEquals('15°C', $structured_output['temperature'], 'Weather function call structured output has the correct temperature for London in Celsius.');

    // Test resetting the structured output.
    $weather->setStructuredOutput([
      'city' => 'Los Angeles',
      'country' => 'USA',
      'unit' => 'fahrenheit',
      'temperature' => '77°F',
    ]);

    // The readable output should be updated.
    $this->assertEquals('77°F', $weather->getReadableOutput(), 'Weather function call readable output is updated after setting structured output.');
    // The structured output should also be updated.
    $structured_output = $weather->getStructuredOutput();
    $this->assertEquals('Los Angeles', $structured_output['city'], 'Weather function call structured output has the updated city.');
    $this->assertEquals('USA', $structured_output['country'], 'Weather function call structured output has the updated country.');
    $this->assertEquals('fahrenheit', $structured_output['unit'], 'Weather function call structured output has the updated unit.');
    $this->assertEquals('77°F', $structured_output['temperature'], 'Weather function call structured output has the updated temperature for Los Angeles in Fahrenheit.');
  }

  /**
   * Test that calculator breaks because it is not structured.
   */
  public function testCalculatorFunctionCallNotStructured(): void {
    $calculator = $this->functionCallManager->createInstance('ai:calculator');
    $this->assertInstanceOf(Calculator::class, $calculator, 'Calculator function call plugin instance is created.');

    // Call the function with two numbers.
    $calculator->setContextValue('expression', '10+5');

    // Execute the function call.
    $calculator->execute();

    // The readable output should be a string.
    $this->assertIsString($calculator->getReadableOutput(), 'Calculator function call has a string output.');
    $this->assertEquals('15', $calculator->getReadableOutput(), 'Calculator function call returns the expected result of 10 + 5.');

    // It will be an empty array because it does not implement output.
    $result = $calculator->getStructuredOutput();
    $this->assertIsArray($result, 'Calculator function call has a structured output.');
    $this->assertEmpty($result, 'Calculator function call structured output is empty because it does not implement structured output.');
    // It should not be a structured executable function call.
    $this->assertTrue($calculator instanceof StructuredExecutableFunctionCallInterface === FALSE, 'Calculator function call is not a structured executable function call.');
  }

}
