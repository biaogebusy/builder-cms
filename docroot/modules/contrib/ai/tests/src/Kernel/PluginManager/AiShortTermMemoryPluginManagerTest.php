<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Kernel\PluginManager;

use Drupal\KernelTests\KernelTestBase;
use Drupal\ai\OperationType\Chat\ChatMessage;
use Drupal\ai\OperationType\Chat\Tools\ToolsFunctionInput;

/**
 * This tests the action plugin deriver.
 *
 * @coversDefaultClass \Drupal\ai\PluginManager\AiShortTermMemoryPluginManager
 *
 * @group ai
 */
class AiShortTermMemoryPluginManagerTest extends KernelTestBase {

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
    'system',
    'node',
    'user',
  ];

  /**
   * Test the test plugin.
   */
  public function testBunnyPlugin(): void {
    // Set up a chat history.
    $chat_history = [
      new ChatMessage('Hello', 'user'),
      new ChatMessage('Hi there! How can I assist you today?', 'assistant'),
      new ChatMessage('Can you tell me a joke about a bunny?', 'user'),
      new ChatMessage('Sure! Why did the bunny go to the party? Because he heard it was going to be a hopping good time!', 'assistant'),
      new ChatMessage('That was a good one. Do you know any fun facts about bunnies?', 'user'),
      new ChatMessage("Absolutely! Did you know that rabbits can't vomit? Their teeth never stop growing, so they have to keep gnawing to wear them down.", 'assistant'),
      new ChatMessage("Wow, I didn't know that! Thanks for the info.", 'user'),
      new ChatMessage("You're welcome! If you have any more questions or need assistance, feel free to ask.", 'assistant'),
    ];

    // Set a system prompt with some word bunny in it.
    $system_prompt = 'You are a helpful assistant that loves bunnies. The bunny is your favorite animal.';

    // Some tools, an array of ToolsFunctionInputInterface.
    $tools = [
      new ToolsFunctionInput('funny bunny facts', []),
      new ToolsFunctionInput('bunny joke', []),
      new ToolsFunctionInput('bunny care tips', []),
    ];
    // Create the plugin.
    $plugin = \Drupal::service('plugin.manager.ai.short_term_memory')->createInstance('remove_bunny');
    $plugin->process(
      thread_id: 'test-thread',
      consumer: 'test-consumer',
      chat_history: $chat_history,
      system_prompt: $system_prompt,
      tools: $tools,
      original_chat_history: $chat_history,
      original_system_prompt: $system_prompt,
      original_tools: $tools,
      request_id: 'test-request',
    );

    // Assert that the chat history is now only the last 5 messages.
    $this->assertCount(5, $plugin->getChatHistory());

    // Assert that the system prompt no longer contains the word bunny.
    $this->assertStringNotContainsString('bunny', $plugin->getSystemPrompt());

    // Assert that there are now only 2 tools left.
    $this->assertCount(2, $plugin->getTools());

  }

}
