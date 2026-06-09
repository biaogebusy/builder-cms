<?php

namespace Drupal\Tests\ai\Unit\Element;

use Drupal\ai\Element\ChatHistory;
use Drupal\Core\Form\FormState;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the chat_history form element.
 *
 * @group ai
 * @covers \Drupal\ai\Element\ChatHistory
 */
class ChatHistoryTest extends TestCase {

  /**
   * Tests that processChatHistory builds elements from provided values.
   */
  public function testProcessChatHistoryBuildsElements(): void {
    $form_state = new FormState();

    $element = [
      '#type' => 'chat_history',
      '#parents' => ['chat'],
      '#name' => 'chat',
      '#wrapper_id' => 'chat-history-wrapper-test',
      '#value' => [
        [
          'role' => 'user',
          'content' => 'Hello',
        ],
        [
          'role' => 'assistant',
          'content' => 'Hi! How can I help?',
          'tool_calls' => [
            [
              'tool_call_id' => 'call-1',
              'function_name' => 'lookup',
              'function_input' => '{"q":"x"}',
            ],
          ],
        ],
      ],
    ];

    $complete_form = [];
    $built = ChatHistory::processChatHistory($element, $form_state, $complete_form);

    // Wrapper and add more button are present.
    $this->assertArrayHasKey('#prefix', $built);
    $this->assertArrayHasKey('add_more', $built);

    // Two messages were built.
    $this->assertArrayHasKey(0, $built);
    $this->assertArrayHasKey(1, $built);

    // Message fields exist.
    $this->assertArrayHasKey('role', $built[0]);
    $this->assertArrayHasKey('content', $built[0]);
    $this->assertArrayHasKey('weight', $built[0]);

    // Assistant message should include tool_calls container & add more button.
    $this->assertArrayHasKey('tool_calls', $built[1]);
    $this->assertArrayHasKey('add_more_tool_call', $built[1]['tool_calls']);
  }

  /**
   * Tests ajaxCallback returns the expected parent element.
   */
  public function testAjaxCallbackReturnsParentElement(): void {
    $form_state = new FormState();

    $form = [
      'example_form' => [
        'chat' => [
          '#type' => 'chat_history',
          '#parents' => ['chat'],
          '#name' => 'chat',
          '#wrapper_id' => 'chat-history-wrapper-test',
        ],
      ],
    ];

    // Simulate the 'add_more' button triggering inside example_form -> chat.
    $trigger = [
      '#name' => 'chat_add_more',
      '#array_parents' => ['example_form', 'chat', 'add_more'],
    ];
    $form_state->setTriggeringElement($trigger);

    $result = ChatHistory::ajaxCallback($form, $form_state);
    $this->assertIsArray($result);
    $this->assertArrayHasKey('#type', $result);
    $this->assertSame('chat_history', $result['#type']);
  }

  /**
   * Tests submit handlers set state for removals.
   */
  public function testSubmitHandlersSetRemovalState(): void {
    // Test tool call removal submit handler.
    $form_state = new FormState();
    $trigger_tool = [
      '#name' => 'tool_call_remove_1_0',
      '#array_parents' => ['example_form', 'chat', 1, 'tool_calls', 0, 'remove_tool_call'],
    ];
    $form_state->setTriggeringElement($trigger_tool);
    $form = ['example_form' => ['chat' => []]];
    ChatHistory::removeToolCallSubmit($form, $form_state);
    $this->assertTrue($form_state->get('tool_call_remove_index_1_0'));
    $this->assertTrue($form_state->isRebuilding());
  }

}
