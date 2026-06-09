<?php

declare(strict_types=1);

namespace Drupal\ai\Base;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ai\Plugin\AiShortTermMemory\AiShortTermMemoryInterface;

/**
 * Base class for AI short term memory.
 */
abstract class AiShortTermMemoryPluginBase extends PluginBase implements AiShortTermMemoryInterface {

  /**
   * The thread id of the current call.
   *
   * @var string
   */
  protected string $threadId = '';

  /**
   * The consumer id of the current call.
   *
   * @var string
   */
  protected string $consumerId;

  /**
   * The request id of a single call.
   *
   * @var string|null
   */
  protected ?string $requestId = NULL;

  /**
   * The chat history.
   *
   * @var \Drupal\ai\OperationType\Chat\ChatMessage[]
   */
  protected array $chatHistory = [];

  /**
   * The system prompt.
   *
   * @var string
   */
  protected string $systemPrompt = '';

  /**
   * The tools available for this call.
   *
   * @var \Drupal\ai\OperationType\Chat\Tools\ToolsFunctionInputInterface[]
   */
  protected array $tools = [];

  /**
   * The original chat history.
   *
   * @var \Drupal\ai\OperationType\Chat\ChatMessage[]
   */
  private array $originalChatHistory = [];

  /**
   * The original system prompt.
   *
   * @var string
   */
  private string $originalSystemPrompt = '';

  /**
   * The original tools.
   *
   * @var \Drupal\ai\OperationType\Chat\Tools\ToolsFunctionInputInterface[]
   */
  private array $originalTools = [];

  /**
   * {@inheritdoc}
   */
  final public function process(
    string $thread_id,
    string $consumer,
    array $chat_history,
    string $system_prompt,
    array $tools,
    array $original_chat_history,
    string $original_system_prompt,
    array $original_tools,
    ?string $request_id = NULL,
  ): void {
    $this->setThreadId($thread_id)
      ->setConsumer($consumer)
      ->setChatHistory($chat_history)
      ->setSystemPrompt($system_prompt)
      ->setTools($tools);
    $this->originalChatHistory = $original_chat_history;
    $this->originalSystemPrompt = $original_system_prompt;
    $this->originalTools = $original_tools;
    if ($request_id) {
      $this->setRequestId($request_id);
    }
    // Call the actual processing function.
    $this->doProcess();
  }

  /**
   * {@inheritdoc}
   */
  public function label(): string {
    // Cast the label to a string since it is a TranslatableMarkup object.
    return (string) $this->pluginDefinition['label'];
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->pluginDefinition['description'] ?? '';
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = $configuration + $this->defaultConfiguration();
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getThreadId(): string {
    return $this->threadId;
  }

  /**
   * Sets the thread id of the current call.
   *
   * @param string $thread_id
   *   The thread id.
   */
  public function setThreadId(string $thread_id): static {
    // Thread id can't be an empty string.
    if (empty($thread_id)) {
      throw new \InvalidArgumentException('Thread id cannot be empty.');
    }
    $this->threadId = $thread_id;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getRequestId(): ?string {
    return $this->requestId;
  }

  /**
   * Sets the request id of a single call.
   *
   * @param string $request_id
   *   The request id to set.
   */
  protected function setRequestId(string $request_id): void {
    if (empty($request_id)) {
      throw new \InvalidArgumentException('Request id cannot be empty.');
    }
    $this->requestId = $request_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getConsumer(): string {
    return $this->consumerId;
  }

  /**
   * Sets the consumer of a single call.
   *
   * @param string $consumer
   *   The consumer to set.
   */
  protected function setConsumer(string $consumer): static {
    // If the consumer is empty, throw an exception.
    if (empty($consumer)) {
      throw new \InvalidArgumentException('Consumer cannot be empty.');
    }
    $this->consumerId = $consumer;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getChatHistory(): array {
    return $this->chatHistory;
  }

  /**
   * Sets the chat history.
   *
   * @param array $chat_history
   *   The chat history to set.
   */
  protected function setChatHistory(array $chat_history): static {
    $this->chatHistory = $chat_history;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getSystemPrompt(): string {
    return $this->systemPrompt;
  }

  /**
   * Sets the system prompt.
   *
   * @param string $system_prompt
   *   The system prompt to set.
   */
  protected function setSystemPrompt(string $system_prompt): static {
    $this->systemPrompt = $system_prompt;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getTools(): array {
    return $this->tools;
  }

  /**
   * Sets the tools.
   *
   * @param array $tools
   *   The tools to set.
   */
  protected function setTools(array $tools): static {
    $this->tools = $tools;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getOriginalChatHistory(): array {
    return $this->originalChatHistory;
  }

  /**
   * {@inheritdoc}
   */
  public function getOriginalSystemPrompt(): string {
    return $this->originalSystemPrompt;
  }

  /**
   * {@inheritdoc}
   */
  public function getOriginalTools(): array {
    return $this->originalTools;
  }

  /**
   * {@inheritdoc}
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    // By default, do nothing.
  }

  /**
   * Helper function to get the chat history as an associative array.
   *
   * @return array
   *   An array of stringable chat messages.
   */
  protected function getChatHistoryAssoc(): array {
    $assoc = [];
    foreach ($this->chatHistory as $message) {
      $assoc[] = $message->toArray();
    }
    return $assoc;
  }

  /**
   * Helper function to get the original chat history as an associative array.
   *
   * @return array
   *   An array of stringable chat messages.
   */
  protected function getOriginalChatHistoryAssoc(): array {
    $assoc = [];
    foreach ($this->originalChatHistory as $message) {
      $assoc[] = $message->toArray();
    }
    return $assoc;
  }

  /**
   * Helper function to get the tools as an associative array.
   *
   * @return array
   *   An array of stringable tools.
   */
  protected function getToolsAssoc(): array {
    $assoc = [];
    foreach ($this->tools as $tool) {
      $assoc[] = $tool->renderFunctionArray();
    }
    return $assoc;
  }

  /**
   * Helper function to get the original tools as an associative array.
   *
   * @return array
   *   An array of stringable tools.
   */
  protected function getOriginalToolsAssoc(): array {
    $assoc = [];
    foreach ($this->originalTools as $tool) {
      $assoc[] = $tool->renderFunctionArray();
    }
    return $assoc;
  }

}
