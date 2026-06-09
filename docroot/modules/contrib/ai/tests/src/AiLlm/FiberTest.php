<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\AiLlm;

use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\ChatOutput;
use Drupal\ai\OperationType\Chat\Tools\ToolsFunctionInput;
use Drupal\ai\OperationType\Chat\Tools\ToolsFunctionOutput;
use Drupal\ai\OperationType\Chat\Tools\ToolsInput;
use Drupal\ai\OperationType\Chat\Tools\ToolsPropertyInput;

/**
 * Tests Fiber-based concurrent haiku generation with AiLlm providers.
 *
 * @group ai
 */
class FiberTest extends AiProviderTestBase {

  /**
   * {@inheritdoc}
   */
  public static array $targetModels = ['openai__gpt-4o'];

  /**
   * Data provider for model tests.
   */
  public static function modelProvider(): \Generator {
    foreach (static::getModels() as $model_definition) {
      [$provider_id, $model_id] = explode('__', $model_definition, 2);
      yield $model_definition => [$provider_id, $model_id];
    }
  }

  /**
   * Tests concurrent haiku generation using Fibers.
   *
   * @dataProvider modelProvider
   */
  public function testFiberHaikuGeneration(string $provider_id, string $model): void {
    /** @var \Drupal\ai\OperationType\Chat\ChatInterface $provider */
    $provider = $this->getProvider($provider_id, $model);

    // Define prompts for haiku generation.
    $prompts = [
      'prompt_15' => new ChatInput([
        new ChatMessage('user', 'Write exactly 15 haikus about nature.'),
      ]),
      'prompt_10' => new ChatInput([
        new ChatMessage('user', 'Write exactly 10 haikus about technology.'),
      ]),
      'prompt_5' => new ChatInput([
        new ChatMessage('user', 'Write exactly 5 haiku about friendship.'),
      ]),
      'prompt_1' => new ChatInput([
        new ChatMessage('user', 'Write exactly 1 haiku about Drupal.'),
      ]),
    ];

    // Create fibers for concurrent execution.
    $fibers = [];
    $results = array_fill_keys(array_keys($prompts), NULL);
    $completion_order = [];

    // Start all fibers.
    foreach ($prompts as $key => $input) {
      $fibers[$key] = new \Fiber(fn() => $provider->chat($input, $model));
      $fibers[$key]->start();
    }

    // Collect results as they complete (should be in reverse order).
    while (!empty($fibers)) {
      // Small delay to allow fibers to progress.
      usleep(100);

      foreach ($fibers as $key => $fiber) {
        if ($fiber->isTerminated()) {
          $results[$key] = $fiber->getReturn();
          $completion_order[] = $key;
          unset($fibers[$key]);
        }
        else {
          $fiber->resume();
        }
      }
    }

    // Assert we got all results.
    $this->assertCount(4, $results, 'results');

    // Verify each result is a ChatOutput with valid text.
    foreach ($results as $result) {
      $this->assertInstanceOf(ChatOutput::class, $result);
      $message = $result->getNormalized();
      $this->assertInstanceOf(ChatMessage::class, $message);

      $text = $message->getText();
      $this->assertIsString($text);
      $this->assertNotEmpty($text);
    }

    // Verify results are presented in the correct order when accessed
    // sequentially.
    $ordered_keys = ['prompt_15', 'prompt_10', 'prompt_5', 'prompt_1'];
    $this->assertEquals(array_keys($results), $ordered_keys);

    // Verify completion was in reverse order (1, 3, 5)
    $this->assertSame(['prompt_1', 'prompt_5', 'prompt_10', 'prompt_15'], $completion_order, 'completion order');
  }

  /**
   * Tests that tool calls still work as expected in Fibers.
   *
   * @dataProvider modelProvider
   */
  public function testFiberToolCall(string $provider_id, string $model): void {
    /** @var \Drupal\ai\OperationType\Chat\ChatInterface $provider */
    $provider = $this->getProvider($provider_id, $model);

    $provider->setChatSystemRole('Make all necessary tool calls in a single response.');
    $input = new ChatInput([
      new ChatMessage('user', 'What is the weather in London and New York?'),
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

    $fiber = new \Fiber(fn() => $provider->chat($input, $model));
    $fiber->start();
    while (!$fiber->isTerminated()) {
      // Small delay to allow fibers to progress.
      usleep(100);
      $fiber->resume();
    }

    $result = $fiber->getReturn();
    $this->assertInstanceOf(ChatOutput::class, $result);
    $tools = $result->getNormalized()->getTools();
    $this->assertCount(2, $tools);

    foreach ($tools as $function) {
      $this->assertInstanceOf(ToolsFunctionOutput::class, $function);
      $this->assertEquals('get_current_weather', $function->getName());
      $properties = $function->getArguments();
      $this->assertCount(2, $properties);
      $this->assertEquals('location', $properties[0]->getName());
      $this->assertEquals('unit', $properties[1]->getName());
      // Always returns test on string.
      $this->assertIsString($properties[0]->getValue());
      $this->assertIsString($properties[1]->getValue());
    }
  }

}
