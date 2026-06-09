<?php

namespace Drupal\ai\OperationType\Chat;

use Drupal\Component\Serialization\Json;
use Drupal\ai\Dto\TokenUsageDto;
use Drupal\ai\Event\PostStreamingResponseEvent;
use Drupal\ai\Guardrail\Result\PassResult;
use Drupal\ai\Guardrail\StreamableGuardrailInterface;
use Drupal\ai\OperationType\Chat\Tools\ToolsFunctionOutput;
use Drupal\ai\Traits\OperationType\EventDispatcherTrait;
use Drupal\ai\Traits\OperationType\StreamChatMessageIteratorTrait;

/**
 * Streamed chat message iterator interface.
 */
abstract class StreamedChatMessageIterator implements StreamedChatMessageIteratorInterface {

  use EventDispatcherTrait;
  use StreamChatMessageIteratorTrait;

  /**
   * The iterator.
   *
   * @var \Traversable
   */
  protected $iterator;

  /**
   * The messages.
   *
   * @var array
   *   The stream chat messages.
   */
  protected $messages = [];

  /**
   * The request thread id.
   *
   * @var string
   */
  protected $requestThreadId;

  /**
   * The finish reason.
   *
   * @var string|null
   */
  protected $finishReason = NULL;

  /**
   * The tool calls used.
   *
   * @var array
   */
  protected $toolCalls = [];

  /**
   * The callbacks to run after the stream is finished.
   *
   * @var callable[]
   */
  protected $callbacks = [];

  /**
   * The Chat Message once set.
   *
   * @var \Drupal\ai\OperationType\Chat\ChatMessage|null
   */
  protected $chatMessage = NULL;

  /**
   * Total token usage.
   *
   * @var int|null
   */
  protected $totalTokenUsage = NULL;

  /**
   * Input token usage.
   *
   * @var int|null
   */
  protected $inputTokenUsage = NULL;

  /**
   * Output token usage.
   *
   * @var int|null
   */
  protected $outputTokenUsage = NULL;

  /**
   * Reasoning token usage.
   *
   * @var int|null
   */
  protected $reasoningTokenUsage = NULL;

  /**
   * Cached token usage.
   *
   * @var int|null
   */
  protected $cachedTokenUsage = NULL;

  /**
   * The created chat output after iteration.
   *
   * @var \Drupal\ai\OperationType\Chat\ChatOutput|null
   */
  protected $chatOutput = NULL;

  /**
   * The original input data sent to the provider.
   *
   * Could be a string, array, or other structured input.
   *
   * @var mixed
   */
  protected $input;

  /**
   * General metadata.
   *
   * @var array
   */
  protected array $metadata = [];

  /**
   * The machine name of the provider used for this stream.
   *
   * Example: "openai", "gemini", etc.
   *
   * @var string|null
   */
  protected ?string $providerId = NULL;

  /**
   * The model identifier used for the request.
   *
   * Example: "gpt-4", "gpt-3.5-turbo", "gemini-pro".
   *
   * @var string|null
   */
  protected ?string $modelId = NULL;

  /**
   * The configuration array passed to the provider.
   *
   * Contains provider-specific options like temperature,
   * max tokens, top_p, etc.
   *
   * @var array
   */
  protected array $providerConfiguration = [];

  /**
   * Tags associated with the stream.
   *
   * Used for categorization, debugging, or tracing events.
   *
   * @var array
   */
  protected array $tags = [];

  /**
   * Message buffer.
   *
   * Because we need to be able to filter html and images for html reasons,
   * we will keep a buffer when a html/markdown starts with this, until its
   * finally finished, so that it can run on the full markup.
   *
   * @var string
   */
  protected string $buffer = '';

  /**
   * The max buffer size.
   *
   * We always try to buffer on a natural paragraph, but longer text or inject
   * attack can make it go for longer, so we need a max buffer for streaming.
   *
   * @var int
   */
  private int $maxBufferSize = 100;

  /**
   * The closing pattern we are looking for.
   *
   * @var string
   */
  protected $bufferClosingPattern = '';

  /**
   * Per-guardrail state for streaming guardrail evaluation.
   *
   * Each entry has the shape:
   *   'guardrail' => StreamableGuardrailInterface
   *   'active'    => bool   (TRUE while buffering this guardrail's content)
   *   'buffer'    => string (accumulated text since the start regex matched)
   *
   * @var array<int, array{guardrail: \Drupal\ai\Guardrail\StreamableGuardrailInterface, active: bool, buffer: string, window: string}>
   */
  protected array $streamingGuardrailStates = [];

  /**
   * The maximum number of characters a guardrail buffer may accumulate.
   *
   * When a guardrail's active buffer reaches this size the buffer is
   * force-evaluated even if the stop regex has not yet matched. This prevents
   * unbounded memory growth caused by a misconfigured or never-matching stop
   * regex when the LLM produces a very long response.
   *
   * Default is 8192 characters (~2000 tokens). Callers may lower or raise
   * this value via setMaxGuardrailBufferSize().
   *
   * @var int
   */
  protected int $maxGuardrailBufferSize = 8192;

  /**
   * Constructor.
   */
  public function __construct(\Traversable $iterator) {
    $this->iterator = $iterator;
  }

  /**
   * Sets the original input sent to the provider.
   *
   * @todo Add to constructor in 2.0.0.
   *
   * @param mixed $input
   *   The input data for this request.
   */
  public function setInput($input): void {
    $this->input = $input;
  }

  /**
   * Gets the original input sent to the provider.
   *
   * @return mixed
   *   The input data.
   */
  public function getInput() {
    return $this->input;
  }

  /**
   * Sets the provider machine name.
   *
   * @todo Add to constructor in 2.0.0.
   *
   * @param string $providerId
   *   The provider identifier.
   */
  public function setProviderId(string $providerId): void {
    $this->providerId = $providerId;
  }

  /**
   * Gets the provider machine name.
   *
   * @return string|null
   *   The provider identifier, or NULL if not set.
   */
  public function getProviderId(): ?string {
    return $this->providerId;
  }

  /**
   * Sets the model identifier.
   *
   * @todo Add to constructor in 2.0.0.
   *
   * @param string $modelId
   *   The model ID.
   */
  public function setModelId(string $modelId): void {
    $this->modelId = $modelId;
  }

  /**
   * Gets the model identifier.
   *
   * @return string|null
   *   The model ID, or NULL if not set.
   */
  public function getModelId(): ?string {
    return $this->modelId;
  }

  /**
   * Set the metadata.
   *
   * @todo Add to constructor in 2.0.0.
   *
   * @param array $metadata
   *   An associative array of metadata.
   */
  public function setMetadata(array $metadata): void {
    $this->metadata = $metadata;
  }

  /**
   * Get the metadata.
   *
   * @return array
   *   An associative array of metadata.
   */
  public function getMetadata(): array {
    return $this->metadata;
  }

  /**
   * Sets the provider configuration.
   *
   * @todo Add to constructor in 2.0.0.
   *
   * @param array $configuration
   *   Provider-specific configuration options.
   */
  public function setProviderConfiguration(array $configuration): void {
    $this->providerConfiguration = $configuration;
  }

  /**
   * Gets the provider configuration.
   *
   * @return array
   *   Provider configuration array.
   */
  public function getProviderConfiguration(): array {
    return $this->providerConfiguration;
  }

  /**
   * Sets tags for the stream.
   *
   * @todo Add to constructor in 2.0.0.
   *
   * @param array $tags
   *   An array of tags.
   */
  public function setTags(array $tags): void {
    $this->tags = $tags;
  }

  /**
   * Gets tags for the stream.
   *
   * @return array
   *   An array of tags.
   */
  public function getTags(): array {
    return $this->tags;
  }

  /**
   * {@inheritdoc}
   */
  public function getIterator(): \Generator {
    foreach ($this->doIterate() as $data) {
      // Yield each flushed chunk word-by-word so consumers see smooth
      // token-level output regardless of how the URL-safety buffer batches.
      // Empty chunks (still buffering) pass through unchanged.
      if ($data->getText() !== '') {
        foreach ($this->splitIntoWordTokens($data->getText()) as $token) {
          yield new StreamedChatMessage($data->getRole(), $token, [], $data->getTools(), $data->getRaw());
        }
      }
      else {
        yield $data;
      }
    }
    // Flush the URL-safety buffer at the end of the stream.
    if (!empty($this->buffer)) {
      $text_message = $this->flushInternal();
      $text_message = $this->processStreamingGuardrails($text_message);
      if (!empty($text_message)) {
        $message = new StreamedChatMessage('assistant', $text_message, []);
        $this->messages[] = $message;
        foreach ($this->splitIntoWordTokens($text_message) as $token) {
          yield new StreamedChatMessage('assistant', $token, []);
        }
      }
    }
    // Flush any streaming guardrail buffers still active at end-of-stream.
    foreach ($this->streamingGuardrailStates as &$state) {
      // Evaluate any active guardrail buffer (may be empty if stream ended
      // immediately after the start match with no follow-up chunks).
      if ($state['active']) {
        $buffered = $state['buffer'];
        $state['active'] = FALSE;
        $state['buffer'] = '';
        $result = $state['guardrail']->processStreamedBuffer($buffered);
        $final_text = ($result instanceof PassResult) ? $buffered : $result->getMessage();
        if ($final_text !== '') {
          $message = new StreamedChatMessage('assistant', $final_text, []);
          $this->messages[] = $message;
          foreach ($this->splitIntoWordTokens($final_text) as $token) {
            yield new StreamedChatMessage('assistant', $token, []);
          }
        }
      }
      elseif ($state['buffer'] !== '') {
        // The stream has ended but text remains in the buffer. This can happen
        // when no sentence boundary (period or newline) was found and the start
        // regex never matched. Yield it as-is to prevent data loss.
        $final_text = $state['buffer'];
        $state['buffer'] = '';
        $message = new StreamedChatMessage('assistant', $final_text, []);
        $this->messages[] = $message;
        foreach ($this->splitIntoWordTokens($final_text) as $token) {
          yield new StreamedChatMessage('assistant', $token, []);
        }
      }
    }
    unset($state);
    $this->reconstructChatOutput();
    $this->triggerEvent();

    foreach ($this->callbacks as $callback) {
      if (is_callable($callback)) {
        $return = $callback($this->chatMessage);
        // If the callback return a streamed chat message, we yield it.
        if ($return instanceof StreamedChatMessageIteratorInterface) {
          foreach ($return as $streamed_message) {
            yield $streamed_message;
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function doIterate(): \Generator {
    // We keep this empty, so anyone extending from 1.2.0 can implement
    // their own logic.
    yield $this->createStreamedChatMessage(
      'assistant',
      'Please implement the doIterate method in your provider.',
      [],
    );
  }

  /**
   * Trigger the event on streaming finished.
   */
  public function triggerEvent(): void {
    // Dispatch the event.
    $event = new PostStreamingResponseEvent(
      requestThreadId: $this->requestThreadId ?? '',
      providerId: $this->providerId ?? '',
      operationType: $this->operationType ?? '',
      configuration: $this->configuration ?? [],
      input: $this->input ?? '',
      modelId: $this->modelId ?? '',
      output: $this->chatOutput,
      tags: $this->tags ?? [],
      debugData: $this->debugData ?? [],
      metadata: $this->metadata ?? []
    );

    $this->getEventDispatcher()->dispatch($event, PostStreamingResponseEvent::EVENT_NAME);
  }

  /**
   * {@inheritdoc}
   */
  public function setRequestThreadId(string $request_thread_id): void {
    $this->requestThreadId = $request_thread_id;
  }

  /**
   * {@inheritdoc}
   */
  public function getRequestThreadId(): string {
    return $this->requestThreadId;
  }

  /**
   * {@inheritdoc}
   */
  public function createStreamedChatMessage(
    string $role,
    string $message,
    array $metadata,
    ?array $tools = NULL,
    ?array $raw = NULL,
  ): StreamedChatMessageInterface {
    // Check if we need to buffer the output (URL-safety buffer).
    $this->buffer .= $message;
    // Return empty chunk if the URL-safety buffer has not flushed yet.
    if (!$this->shouldFlush()) {
      $msg = new StreamedChatMessage($role, '', $metadata, $tools, $raw);
      $this->messages[] = $msg;
      return $msg;
    }
    $text_message = $this->flushInternal();
    // Run the flushed text through any registered streaming guardrails.
    $text_message = $this->processStreamingGuardrails($text_message);
    $msg = new StreamedChatMessage($role, $text_message, $metadata, $tools, $raw);
    $this->messages[] = $msg;
    return $msg;
  }

  /**
   * {@inheritdoc}
   */
  public function getStreamChatMessages(): array {
    return $this->messages;
  }

  /**
   * {@inheritdoc}
   */
  public function addCallback(callable $callback): void {
    $this->callbacks[] = $callback;
  }

  /**
   * {@inheritdoc}
   */
  public function setStreamChatMessages(array $messages): void {
    $this->messages = $messages;
  }

  /**
   * {@inheritdoc}
   */
  public function reconstructChatOutput(): ChatOutput {
    // Create a ChatMessage out of it all.
    $role = '';
    $message_text = '';
    $raw = [];
    foreach ($this->messages as $message) {
      // The role only needs to be set once, so we check if it's empty.
      if (!empty($message->getRole()) && empty($role)) {
        $role = $message->getRole();
      }
      // Just accumulate the text.
      if (!empty($message->getText())) {
        $message_text .= $message->getText();
      }
      // We assume that we can combine, any external provider can override this
      // if needed.
      if (!empty($message->getRaw())) {
        $raw = array_merge($raw, $message->getRaw());
      }

      // Set the total usage, if it exists.
      $this->setTokenUsageFromChunk($message);
    }

    $message = new ChatMessage($role, $message_text);
    $message->setTools($this->assembleToolCalls());

    // Set the chat message.
    $this->chatMessage = $message;

    $output = new ChatOutput($message, $raw, []);
    // Set the token usage on the output.
    $output = $this->setTokenUsageOnChatOutput($output);

    $this->chatOutput = $output;
    return $output;
  }

  /**
   * {@inheritdoc}
   */
  public function getTools(): array {
    return $this->assembleToolCalls();
  }

  /**
   * Assembled the tools calls used - OpenAI style, can be overriden.
   *
   * @return array
   *   The tool calls used.
   */
  private function assembleToolCalls(): array {
    $tools = [];
    $key = 0;
    $current_tool = NULL;
    foreach ($this->messages as $message) {
      if ($message->getTools()) {
        foreach ($message->getTools() as $tool) {
          $array_tool = $tool->toArray();
          // If it has a new id, it means its a new tool call.
          if (!empty($array_tool['id'])) {
            // If the current tool is not empty, we need to save it.
            if (!empty($current_tool)) {
              $arguments = Json::decode($current_tool['function']['arguments']);
              $output = new ToolsFunctionOutput(NULL, $current_tool['id'], $arguments);
              $output->setName($current_tool['function']['name']);
              $tools[$key] = $output;
              $key++;
            }
            // Reset the current tool.
            $current_tool = $array_tool;
          }
          else {
            // Otherwise we just add to the argument of the current tool.
            $current_tool['function']['arguments'] .= $array_tool['function']['arguments'] ?? '';
          }
        }
      }
    }
    // Save the last tool if it exists.
    if (!empty($current_tool)) {
      $arguments = Json::decode($current_tool['function']['arguments']);
      $output = new ToolsFunctionOutput(NULL, $current_tool['id'], $arguments);
      $output->setName($current_tool['function']['name']);
      $tools[$key] = $output;
    }

    return $tools;
  }

  /**
   * Set the token usage from each chunk.
   *
   * @param \Drupal\ai\OperationType\Chat\StreamedChatMessageInterface $message
   *   The streamed chat message to set the token usage on.
   */
  protected function setTokenUsageFromChunk(StreamedChatMessageInterface $message): void {
    if ($message->getTotalTokenUsage() !== NULL) {
      $this->totalTokenUsage = $message->getTotalTokenUsage();
    }
    if ($message->getInputTokenUsage() !== NULL) {
      $this->inputTokenUsage = $message->getInputTokenUsage();
    }
    if ($message->getOutputTokenUsage() !== NULL) {
      $this->outputTokenUsage = $message->getOutputTokenUsage();
    }
    if ($message->getReasoningTokenUsage() !== NULL) {
      $this->reasoningTokenUsage = $message->getReasoningTokenUsage();
    }
    if ($message->getCachedTokenUsage() !== NULL) {
      $this->cachedTokenUsage = $message->getCachedTokenUsage();
    }
  }

  /**
   * Set the token usage on the chat output.
   *
   * @param \Drupal\ai\OperationType\Chat\ChatOutput $output
   *   The chat output to set the token usage on.
   *
   * @return \Drupal\ai\OperationType\Chat\ChatOutput
   *   The chat output with the token usage set.
   */
  protected function setTokenUsageOnChatOutput(ChatOutput $output): ChatOutput {
    $output->setTokenUsage(new TokenUsageDto(
      total: $this->totalTokenUsage,
      input: $this->inputTokenUsage,
      output: $this->outputTokenUsage,
      reasoning: $this->reasoningTokenUsage,
      cached: $this->cachedTokenUsage
    ));
    return $output;
  }

  /**
   * {@inheritdoc}
   */
  public function setFinishReason(string $finished_reason): void {
    $this->finishReason = $finished_reason;
  }

  /**
   * {@inheritdoc}
   */
  public function getFinishReason(): ?string {
    return $this->finishReason;
  }

  /**
   * {@inheritdoc}
   */
  public function getMaxBufferSize(): int {
    return $this->maxBufferSize;
  }

  /**
   * {@inheritDoc}
   */
  public function setMaxBufferSize(int $size): void {
    $this->maxBufferSize = $size;
  }

  /**
   * {@inheritdoc}
   */
  public function getMaxGuardrailBufferSize(): int {
    return $this->maxGuardrailBufferSize;
  }

  /**
   * {@inheritdoc}
   */
  public function setMaxGuardrailBufferSize(int $size): void {
    $this->maxGuardrailBufferSize = $size;
  }

  /**
   * {@inheritdoc}
   */
  public function addStreamingGuardrail(StreamableGuardrailInterface $guardrail): void {
    $this->streamingGuardrailStates[] = [
      'guardrail' => $guardrail,
      'active' => FALSE,
      'buffer' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getStreamingGuardrails(): array {
    return array_column($this->streamingGuardrailStates, 'guardrail');
  }

  /**
   * Passes a flushed text chunk through all registered streaming guardrails.
   *
   * Each guardrail independently manages its own start/stop state.
   *
   * Before the guardrail activates, incoming chunks are appended to the buffer
   * but only content up to the last sentence boundary (period or newline) is
   * flushed to the consumer. The tail is held back so that start patterns
   * split across chunk boundaries are still detected reliably.
   *
   * Once buffering is active, all content is suppressed until the stop regex
   * matches the accumulated buffer (or the stream ends). If the buffer grows
   * beyond $maxGuardrailBufferSize the buffer is force-evaluated to prevent
   * unbounded memory growth.
   *
   * Guardrails are applied in registration order; each guardrail sees the
   * output of the previous one.
   *
   * @param string $text
   *   The text chunk to process.
   *
   * @return string
   *   The (possibly suppressed or rewritten) text to yield to the consumer.
   */
  protected function processStreamingGuardrails(string $text): string {
    if (empty($this->streamingGuardrailStates)) {
      return $text;
    }

    foreach ($this->streamingGuardrailStates as &$state) {
      /** @var \Drupal\ai\Guardrail\StreamableGuardrailInterface $guardrail */
      $guardrail = $state['guardrail'];

      if ($state['active']) {
        // A previous chunk has triggered this guardrail. The buffered text will
        // be evaluated once the stop regex matches or maxGuardrailBufferSize is
        // exceeded. First, append the current chunk to the buffer.
        $state['buffer'] .= $text;
        $text = '';
        $stop_regex = $guardrail->getStopRegex();
        // Checks if the text in the buffer has exceeded the max guardrail
        // buffer size.
        $force_flush = strlen($state['buffer']) >= $this->maxGuardrailBufferSize;

        if ($force_flush || ($stop_regex !== '' && preg_match($stop_regex, $state['buffer']))) {
          // Stop pattern matched or buffer exceeded max size. Evaluate
          // the guardrail on the buffered content and reset state.
          $buffered = $state['buffer'];
          // Deactivate the guardrail since buffering is complete. It will
          // reactivate once the start regex matches again.
          $state['active'] = FALSE;
          $state['buffer'] = '';
          $result = $guardrail->processStreamedBuffer($buffered);
          // Return the processed output to continue the stream.
          $text = ($result instanceof PassResult) ? $buffered : $result->getMessage();
        }
      }
      else {
        // Guardrail is not active. Check if it should become active based
        // on the start regex.
        $start_regex = $guardrail->getStartRegex();

        if ($start_regex === '') {
          // An empty start regex means the guardrail should activate on the
          // very first token the model outputs. Store the current chunk in the
          // guardrail buffer for evaluation instead of passing it through.
          // Activate the guardrail so it evaluates buffered text once the stop
          // regex matches or the buffer exceeds maxGuardrailBufferSize.
          $state['active'] = TRUE;
          // Store the current chunk in the guardrail buffer.
          $state['buffer'] = $text;
          // Clear the outgoing text so nothing is streamed to the user. The
          // current content is held in the buffer until the stop regex matches
          // or the buffer exceeds maxGuardrailBufferSize.
          $text = '';
        }
        else {
          // Append the current chunk to the buffer. The buffer holds all
          // unsent content accumulated since the guardrail became inactive.
          // Nothing in the buffer has been shown to the user yet.
          $state['buffer'] .= $text;

          // Check if the accumulated unsent content matches the start regex.
          if (preg_match($start_regex, $state['buffer'])) {
            // Start pattern found. Activate the guardrail — the buffer
            // already holds all unsent content and will be evaluated once
            // the stop regex matches or the buffer exceeds
            // maxGuardrailBufferSize.
            $state['active'] = TRUE;
            $text = '';
          }
          else {
            // No start regex match yet. We cannot safely stream the entire
            // buffer because the start pattern might begin in this chunk and
            // complete in the next one (e.g. a word split mid-chunk). Instead,
            // find the last sentence boundary (period or newline) in the
            // buffer. Everything up to that boundary is safe to stream because
            // no sentence-spanning pattern can start after a completed
            // sentence. The tail after the boundary is kept in the buffer and
            // re-evaluated when the next chunk arrives.
            $boundary = max(
              ($p = strrpos($state['buffer'], '.')) !== FALSE ? $p : -1,
              ($p = strrpos($state['buffer'], "\n")) !== FALSE ? $p : -1,
            );

            if ($boundary >= 0) {
              // Stream the safe portion up to and including the boundary
              // character.
              $text = substr($state['buffer'], 0, $boundary + 1);
              // Keep only the tail (after the boundary) in the buffer.
              $state['buffer'] = substr($state['buffer'], $boundary + 1);
            }
            else {
              // No sentence boundary found anywhere in the buffer. Hold
              // everything and stream nothing until a boundary or start
              // regex appears.
              $text = '';
            }

            // If the buffer has grown beyond maxGuardrailBufferSize without a
            // sentence boundary or start regex match, the content is clearly
            // safe to stream. Flush it all to the user and start fresh.
            if (strlen($state['buffer']) > $this->maxGuardrailBufferSize) {
              $text .= $state['buffer'];
              $state['buffer'] = '';
            }
          }
        }
      }
    }
    unset($state);

    return $text;
  }

  /**
   * Splits text into word-level tokens for smooth consumer output.
   *
   * Each token is either a word (including trailing whitespace) or a run of
   * whitespace-only characters, preserving the original text exactly so that
   * concatenating all tokens reproduces the input.
   *
   * @param string $text
   *   The text to split.
   *
   * @return string[]
   *   Ordered array of tokens.
   */
  private function splitIntoWordTokens(string $text): array {
    preg_match_all('/[\p{Han}\p{Hiragana}\p{Katakana}\p{Hangul}]|\S+\s*|\s+/u', $text, $matches);
    return $matches[0];
  }

  /**
   * Flushes buffer and sanitizes content.
   *
   * @return string
   *   Sanitized HTML output.
   */
  private function flushInternal(): string {
    $raw = $this->buffer;
    $this->buffer = '';

    return $this->getHostnameFilterService()->filterText($raw);
  }

  /**
   * Determines whether buffer is safe to flush.
   *
   * @return bool
   *   TRUE if buffer should flush.
   */
  private function shouldFlush(): bool {
    // If a URL is being built, hold the buffer until it's complete, so we can
    // check the full url for safety.
    if (preg_match('/"(?:http|\/\/:|\/)[^"]*$/', $this->buffer) || preg_match('/\((?:http|\/\/:|\/)[^)]*$/', $this->buffer)) {
      return FALSE;
    }

    // Paragraph or block boundary.
    if (str_contains($this->buffer, "\n")) {
      return TRUE;
    }

    // It reached max buffer size.
    if (strlen($this->buffer) >= $this->maxBufferSize) {
      return TRUE;
    }

    return FALSE;
  }

}
