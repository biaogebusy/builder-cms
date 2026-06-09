<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Kernel\OperationType\Chat\Tools;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ChatOutput;
use Drupal\ai\OperationType\Chat\Tools\ToolsFunctionInput;
use Drupal\ai\OperationType\Chat\Tools\ToolsFunctionOutput;
use Drupal\ai\OperationType\Chat\Tools\ToolsInput;
use Drupal\ai\OperationType\Chat\Tools\ToolsPropertyInput;

/**
 * This tests the Chat calling.
 *
 * @coversDefaultClass \Drupal\ai\OperationType\Chat\Tools\ToolsFunctionInput
 *
 * @group ai
 */
class ToolsInterfaceTest extends KernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'ai',
    'ai_test',
    'key',
    'file',
    'user',
    'field',
    'system',
  ];

  /**
   * Setup the test.
   */
  protected function setUp(): void {
    parent::setUp();

    // Install entity schemas.
    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installSchema('file', [
      'file_usage',
    ]);
    $this->installEntitySchema('ai_mock_provider_result');
  }

  /**
   * Test chat tools.
   */
  public function testChatTools(): void {
    $text = 'What is the temperature in Florida?';
    $provider = \Drupal::service('ai.provider')->createInstance('echoai');
    $input = new ChatInput([
      new ChatMessage('user', $text),
    ]);
    // Create our tools.
    $property1 = new ToolsPropertyInput('location', [
      'description' => 'The city and state, e.g. San Francisco, CA',
      'type' => 'string',
    ]);
    $property2 = new ToolsPropertyInput('unit', [
      'enum' => ['fahrenheit', 'celsius'],
      'type' => 'string',
    ]);
    $function = new ToolsFunctionInput('get_current_weather', [
      'description' => 'Get the current weather for a location.',
      'properties' => [$property1, $property2],
    ]);
    $tools = new ToolsInput([$function]);
    $input->setChatTools($tools);
    $chat_response = $provider->chat($input, 'test');
    // Should be a ChatOutput object.
    $this->assertInstanceOf(ChatOutput::class, $chat_response);
    // Should have a message.
    $message = $chat_response->getNormalized();
    $this->assertInstanceOf(ChatMessage::class, $message);

    // Response should be a string and be the following.
    $response_text = "Hello world! Input: $text. Config: [].";
    $this->assertIsString($message->getText());
    $this->assertEquals($response_text, $message->getText());

    // Should return the first tools with the test values.
    foreach ($message->getTools() as $tools) {
      foreach ($tools->getFunctions() as $function) {
        $this->assertInstanceOf(ToolsFunctionOutput::class, $function);
        $this->assertEquals('get_current_weather', $function->getName());
        $properties = $function->getArguments();
        $this->assertCount(2, $properties);
        $this->assertEquals('location', $properties[0]->getName());
        $this->assertEquals('unit', $properties[1]->getName());
        // Always returns test on string.
        $this->assertEquals('test', $properties[0]->getValue());
        $this->assertEquals('test', $properties[1]->getValue());
      }
      // Make sure it validates.
      $function->validate();
    }
  }

}
