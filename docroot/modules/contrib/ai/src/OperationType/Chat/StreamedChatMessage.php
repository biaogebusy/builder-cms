<?php

namespace Drupal\ai\OperationType\Chat;

/**
 * Streamed chat message.
 */
class StreamedChatMessage implements StreamedChatMessageInterface {

  /**
   * The role.
   *
   * @var string
   */
  private string $role;

  /**
   * The text.
   *
   * @var string
   */
  private string $text;

  /**
   * The metadata.
   *
   * @var array
   */
  private array $metadata;

  /**
   * The tools.
   *
   * @var array|null
   */
  private ?array $tools = NULL;

  /**
   * The raw data.
   *
   * @var array|null
   */
  private ?array $raw = NULL;

  /**
   * The amount of input tokens from the AI provider.
   */
  private ?int $inputTokensUsage = NULL;

  /**
   * The amount of output tokens from the AI provider.
   */
  private ?int $outputTokensUsage = NULL;

  /**
   * The amount of total tokens from the AI provider.
   */
  private ?int $totalTokensUsage = NULL;

  /**
   * The amount of reasoning tokens from the AI provider.
   */
  private ?int $reasoningTokensUsage = NULL;

  /**
   * The amount of cached tokens from the AI provider.
   */
  private ?int $cachedTokensUsage = NULL;

  /**
   * Constructor.
   */
  public function __construct(string $role = "", string $text = "", array $metadata = [], ?array $tools = NULL, ?array $raw = NULL) {
    $this->role = $role;
    $this->text = $text;
    $this->metadata = $metadata;
    if (!empty($tools)) {
      $this->tools = $tools;
    }
    if (!empty($raw)) {
      $this->raw = $raw;
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getText(): string {
    return $this->text;
  }

  /**
   * {@inheritdoc}
   */
  public function setText(string $text): void {
    $this->text = $text;
  }

  /**
   * {@inheritdoc}
   */
  public function getRole(): string {
    return $this->role;
  }

  /**
   * {@inheritdoc}
   */
  public function setRole(string $role): void {
    $this->role = $role;
  }

  /**
   * {@inheritdoc}
   */
  public function getTools(): ?array {
    return $this->tools;
  }

  /**
   * {@inheritdoc}
   */
  public function setTools(?array $tools): void {
    $this->tools = $tools;
  }

  /**
   * {@inheritdoc}
   */
  public function getRaw(): ?array {
    return $this->raw;
  }

  /**
   * {@inheritdoc}
   */
  public function setRaw(?array $raw): void {
    $this->raw = $raw;
  }

  /**
   * {@inheritdoc}
   */
  public function getMetadata(): array {
    return $this->metadata;
  }

  /**
   * {@inheritdoc}
   */
  public function setMetadata(array $metadata): void {
    $this->metadata = $metadata;
  }

  /**
   * {@inheritdoc}
   */
  public function setTotalTokenUsage(int $tokens): void {
    $this->totalTokensUsage = $tokens;
  }

  /**
   * {@inheritdoc}
   */
  public function setInputTokenUsage(int $tokens): void {
    $this->inputTokensUsage = $tokens;
  }

  /**
   * {@inheritdoc}
   */
  public function setOutputTokenUsage(int $tokens): void {
    $this->outputTokensUsage = $tokens;
  }

  /**
   * {@inheritdoc}
   */
  public function setReasoningTokenUsage(int $tokens): void {
    $this->reasoningTokensUsage = $tokens;
  }

  /**
   * {@inheritdoc}
   */
  public function setCachedTokenUsage(int $tokens): void {
    $this->cachedTokensUsage = $tokens;
  }

  /**
   * {@inheritdoc}
   */
  public function getTotalTokenUsage(): ?int {
    return $this->totalTokensUsage;
  }

  /**
   * {@inheritdoc}
   */
  public function getInputTokenUsage(): ?int {
    return $this->inputTokensUsage;
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputTokenUsage(): ?int {
    return $this->outputTokensUsage;
  }

  /**
   * {@inheritdoc}
   */
  public function getReasoningTokenUsage(): ?int {
    return $this->reasoningTokensUsage;
  }

  /**
   * {@inheritdoc}
   */
  public function getCachedTokenUsage(): ?int {
    return $this->cachedTokensUsage;
  }

}
