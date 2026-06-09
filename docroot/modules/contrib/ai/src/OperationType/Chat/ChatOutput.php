<?php

namespace Drupal\ai\OperationType\Chat;

use Drupal\ai\OperationType\OutputInterface;
use Drupal\ai\Dto\TokenUsageDto;
use Drupal\ai\Dto\ChatProviderLimitsDto;

/**
 * Data transfer output object for chat output.
 */
class ChatOutput implements OutputInterface {

  /**
   * The chat message.
   *
   * @var \Drupal\ai\OperationType\Chat\ChatMessage|\Drupal\ai\OperationType\Chat\StreamedChatMessageIteratorInterface
   */
  private ChatMessage|StreamedChatMessageIteratorInterface $normalized;

  /**
   * The raw output from the AI provider.
   *
   * @var mixed
   */
  private mixed $rawOutput;

  /**
   * The metadata from the AI provider.
   *
   * @var mixed
   */
  private mixed $metadata;

  /**
   * The token usage DTO.
   */
  private TokenUsageDto $tokenUsage;

  /**
   * The rate limits DTO.
   */
  private ChatProviderLimitsDto $rateLimits;

  /**
   * The constructor.
   *
   * @param \Drupal\ai\OperationType\Chat\ChatMessage|\Drupal\ai\OperationType\Chat\StreamedChatMessageIteratorInterface $normalized
   *   The chat message.
   * @param mixed $rawOutput
   *   The raw output from the AI provider.
   * @param mixed $metadata
   *   The metadata from the AI provider.
   * @param \Drupal\ai\Dto\TokenUsageDto|null $tokenUsage
   *   The token usage.
   */
  public function __construct(ChatMessage|StreamedChatMessageIteratorInterface $normalized, mixed $rawOutput, mixed $metadata, ?TokenUsageDto $tokenUsage = NULL) {
    $this->normalized = $normalized;
    $this->rawOutput = $rawOutput;
    $this->metadata = $metadata;
    $this->tokenUsage = $tokenUsage ?? new TokenUsageDto();
    $this->rateLimits = new ChatProviderLimitsDto();
  }

  /**
   * Returns the new chat message.
   *
   * @return \Drupal\ai\OperationType\Chat\ChatMessage|\Drupal\ai\OperationType\Chat\StreamedChatMessageIteratorInterface
   *   The text string.
   */
  public function getNormalized(): ChatMessage|StreamedChatMessageIteratorInterface {
    return $this->normalized;
  }

  /**
   * Gets the raw output from the AI provider.
   *
   * @return mixed
   *   The raw output.
   */
  public function getRawOutput(): mixed {
    return $this->rawOutput;
  }

  /**
   * Gets the metadata from the AI provider.
   *
   * @return mixed
   *   The metadata.
   */
  public function getMetadata(): mixed {
    return $this->metadata;
  }

  /**
   * Set the token usage.
   *
   * @param \Drupal\ai\Dto\TokenUsageDto $tokenUsage
   *   The token usage.
   */
  public function setTokenUsage(TokenUsageDto $tokenUsage): void {
    $this->tokenUsage = $tokenUsage;
  }

  /**
   * Gets the token usage.
   *
   * @return \Drupal\ai\Dto\TokenUsageDto
   *   The token usage.
   */
  public function getTokenUsage(): TokenUsageDto {
    return $this->tokenUsage;
  }

  /**
   * Set the rate limits.
   *
   * @param \Drupal\ai\Dto\ChatProviderLimitsDto $rateLimits
   *   The rate limits.
   */
  public function setRateLimits(ChatProviderLimitsDto $rateLimits): void {
    $this->rateLimits = $rateLimits;
  }

  /**
   * Gets the rate limits.
   *
   * @return \Drupal\ai\Dto\ChatProviderLimitsDto
   *   The rate limits.
   */
  public function getRateLimits(): ChatProviderLimitsDto {
    return $this->rateLimits;
  }

  /**
   * Set the total tokens used by the AI provider.
   *
   * @param int $tokens
   *   The amount of tokens.
   *
   * @deprecated in ai:1.2.0 and is removed from ai:2.0.0. Use
   * setTokenUsage() in the ChatOutput class instead.
   * @see https://www.drupal.org/project/ai/issues/3541284
   */
  public function setTotalTokenUsage(?int $tokens): void {
    $this->tokenUsage->total = $tokens;
  }

  /**
   * Set the input tokens used by the AI provider.
   *
   * @param int $tokens
   *   The amount of tokens.
   *
   * @deprecated in ai:1.2.0 and is removed from ai:2.0.0. Use
   * setTokenUsage() in the ChatOutput class instead.
   * @see https://www.drupal.org/project/ai/issues/3541284
   */
  public function setInputTokenUsage(?int $tokens): void {
    $this->tokenUsage->input = $tokens;
  }

  /**
   * Set the output tokens used by the AI provider.
   *
   * @param int $tokens
   *   The amount of tokens.
   *
   * @deprecated in ai:1.2.0 and is removed from ai:2.0.0. Use
   * setTokenUsage() in the ChatOutput class instead.
   * @see https://www.drupal.org/project/ai/issues/3541284
   */
  public function setOutputTokenUsage(?int $tokens): void {
    $this->tokenUsage->output = $tokens;
  }

  /**
   * Set the reasoning tokens used by the AI provider.
   *
   * @param int $tokens
   *   The amount of tokens.
   *
   * @deprecated in ai:1.2.0 and is removed from ai:2.0.0. Use
   * setTokenUsage() in the ChatOutput class instead.
   * @see https://www.drupal.org/project/ai/issues/3541284
   */
  public function setReasoningTokenUsage(?int $tokens): void {
    $this->tokenUsage->reasoning = $tokens;
  }

  /**
   * Set the cached tokens used by the AI provider.
   *
   * @param int $tokens
   *   The amount of tokens.
   *
   * @deprecated in ai:1.2.0 and is removed from ai:2.0.0. Use
   * setTokenUsage() in the ChatOutput class instead.
   * @see https://www.drupal.org/project/ai/issues/3541284
   */
  public function setCachedTokenUsage(?int $tokens): void {
    $this->tokenUsage->cached = $tokens;
  }

  /**
   * Gets the total tokens used by the AI provider.
   *
   * @deprecated in ai:1.2.0 and is removed from ai:2.0.0. Use
   * getTokenUsage() in the ChatOutput class instead.
   * @see https://www.drupal.org/project/ai/issues/3541284
   *
   * @return int|null
   *   The total token usage.
   */
  public function getTotalTokenUsage(): ?int {
    return $this->tokenUsage->total;
  }

  /**
   * Gets the input tokens used by the AI provider.
   *
   * @deprecated in ai:1.2.0 and is removed from ai:2.0.0. Use
   * getTokenUsage() in the ChatOutput class instead.
   * @see https://www.drupal.org/project/ai/issues/3541284
   *
   * @return int|null
   *   The input token usage.
   */
  public function getInputTokenUsage(): ?int {
    return $this->tokenUsage->input;
  }

  /**
   * Gets the output tokens used by the AI provider.
   *
   * @deprecated in ai:1.2.0 and is removed from ai:2.0.0. Use
   * getTokenUsage() in the ChatOutput class instead.
   * @see https://www.drupal.org/project/ai/issues/3541284
   *
   * @return int|null
   *   The output token usage.
   */
  public function getOutputTokenUsage(): ?int {
    return $this->tokenUsage->output;
  }

  /**
   * Gets the reasoning tokens used by the AI provider.
   *
   * @deprecated in ai:1.2.0 and is removed from ai:2.0.0. Use
   * getTokenUsage() in the ChatOutput class instead.
   * @see https://www.drupal.org/project/ai/issues/3541284
   *
   * @return int|null
   *   The reasoning token usage.
   */
  public function getReasoningTokenUsage(): ?int {
    return $this->tokenUsage->reasoning;
  }

  /**
   * Gets the cached tokens used by the AI provider.
   *
   * @deprecated in ai:1.2.0 and is removed from ai:2.0.0. Use
   * getTokenUsage() in the ChatOutput class instead.
   * @see https://www.drupal.org/project/ai/issues/3541284
   *
   * @return int|null
   *   The cached token usage.
   */
  public function getCachedTokenUsage(): ?int {
    return $this->tokenUsage->cached;
  }

  /**
   * {@inheritdoc}
   */
  public function toArray(): array {
    if ($this->normalized instanceof StreamedChatMessageIteratorInterface) {
      return [];
    }
    return [
      'normalized' => $this->normalized->toArray(),
      'rawOutput' => $this->rawOutput,
      'metadata' => $this->metadata,
      'tokenUsage' => $this->tokenUsage->toArray(),
      'rateLimits' => $this->rateLimits->toArray(),
    ];
  }

  /**
   * Create an instance from an array.
   *
   * @param array $data
   *   The data to create the instance from.
   *
   * @return \Drupal\ai\OperationType\Chat\ChatOutput
   *   The output instance.
   */
  public static function fromArray(array $data): ChatOutput {
    $normalized = $data['normalized'] ?? NULL;
    $normalized = ChatMessage::fromArray($normalized);
    $raw_output = $data['rawOutput'] ?? NULL;
    $metadata = $data['metadata'] ?? NULL;
    $tokenUsage = isset($data['tokenUsage']) ? TokenUsageDto::create($data['tokenUsage']) : NULL;
    $rateLimits = isset($data['rateLimits']) ? ChatProviderLimitsDto::create($data['rateLimits']) : NULL;
    $output = new static($normalized, $raw_output, $metadata, $tokenUsage);
    if ($rateLimits) {
      $output->setRateLimits($rateLimits);
    }
    return $output;
  }

}
