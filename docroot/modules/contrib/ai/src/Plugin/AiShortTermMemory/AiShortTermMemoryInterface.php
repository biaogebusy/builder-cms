<?php

declare(strict_types=1);

namespace Drupal\ai\Plugin\AiShortTermMemory;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\PluginFormInterface;

/**
 * Interface for ai_short_term_memory plugins.
 */
interface AiShortTermMemoryInterface extends ConfigurableInterface, PluginInspectionInterface, PluginFormInterface {

  /**
   * Returns the translated plugin label.
   *
   * @return string
   *   The label.
   */
  public function label(): string;

  /**
   * This method takes care of actually transforming the chat history.
   *
   * This will be called just before the AI request is made, and gives the
   * short term memory a chance to modify the chat history, system prompt,
   * and tools as needed.
   */
  public function doProcess(): void;

  /**
   * Gets the thread id of the current call.
   *
   * The thread id is the unique identifier for a conversation group. This is
   * used to group messages together in a conversation and can be used by
   * the short term memory to get its own history using its own storage.
   *
   * @return string
   *   The thread id.
   */
  public function getThreadId(): string;

  /**
   * Gets the request id of a single call.
   *
   * This is completely optional, but if a system has a way to set a unique
   * identifier for each single call, it can be used when storing messages or
   * fetching other data from a system.
   *
   * @return string|null
   *   The request id, or null if not set.
   */
  public function getRequestId(): ?string;

  /**
   * Gets the consumer of a single call.
   *
   * While the main usage of this is agents and assistants, this can also be
   * used for other consumers of the AI system, like content generation or
   * other systems. This makes it possible to have the short term memory
   * behave differently based on the consumer.
   *
   * @return string
   *   The consumer.
   */
  public function getConsumer(): string;

  /**
   * Gets the chat history.
   *
   * This will provide an array of the current set chat history on the call.
   * This means that if the short term memory has modified the history, it will
   * be reflected here. The last call is either the last user message or the
   * last tool result.
   *
   * @return \Drupal\ai\OperationType\Chat\ChatMessage[]
   *   The chat history.
   */
  public function getChatHistory(): array;

  /**
   * Get system prompt.
   *
   * This will return the system prompt for how its currently set. This can be
   * used by the short term memory to modify or append to the system prompt as
   * needed. This means that if the short term memory has modified the system
   * prompt, it will be reflected here.
   *
   * @return string
   *   The system prompt.
   */
  public function getSystemPrompt(): string;

  /**
   * Get the tools.
   *
   * This will return the tools as they are currently set. This can be used by
   * the short term memory to modify or append to the tools as needed. This
   * gives the flexibility to add or remove tools based on the short term
   * memory logic or when we know they will not be used anymore.
   *
   * @return \Drupal\ai\OperationType\Chat\Tools\ToolsFunctionInputInterface[]
   *   The tools.
   */
  public function getTools(): array;

  /**
   * Get the original chat history.
   *
   * This will return the chat history as it was set before any modifications
   * were made by the short term memory.
   *
   * @return \Drupal\ai\OperationType\Chat\ChatMessage[]
   *   The original chat history.
   */
  public function getOriginalChatHistory(): array;

  /**
   * Gets the original system prompt.
   *
   * This will return the system prompt as it was set before any modifications
   * were made by the short term memory.
   *
   * @return string
   *   The original system prompt.
   */
  public function getOriginalSystemPrompt(): string;

  /**
   * Gets the original tools.
   *
   * This will return the tools as they were set before any modifications
   * were made by the short term memory. This might be useful in some cases to
   * see what tools were originally available, if you need to revert back to
   * one of them.
   *
   * @return \Drupal\ai\OperationType\Chat\Tools\ToolsFunctionInputInterface[]
   *   The original tools.
   */
  public function getOriginalTools(): array;

  /**
   * The wrapper method for processing the short term memory.
   *
   * This method will set up all the necessary properties and then call
   * doProcess(), which is where the actual processing logic should be
   * implemented - do not override this method, just extend the base class
   * AiShortTermMemoryPluginBase and implement doProcess().
   */
  public function process(
    string $thread_id,
    string $consumer,
    array $chat_history,
    string $system_prompt,
    array $tools,
    array $original_chat_history,
    string $original_system_prompt,
    array $original_tools,
    ?string $request_id = NULL,
  ): void;

}
