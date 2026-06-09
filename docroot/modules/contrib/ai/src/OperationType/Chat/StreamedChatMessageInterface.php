<?php

namespace Drupal\ai\OperationType\Chat;

/**
 * Streamed chat message interface.
 */
interface StreamedChatMessageInterface {

  /**
   * Get role.
   *
   * @return string
   *   The role.
   */
  public function getRole(): string;

  /**
   * Set role.
   *
   * @param string $role
   *   The role.
   */
  public function setRole(string $role): void;

  /**
   * Get the tools.
   *
   * @return array|null
   *   The tools.
   */
  public function getTools(): ?array;

  /**
   * Set the tools.
   *
   * @param array|null $tools
   *   The tools.
   */
  public function setTools(?array $tools): void;

  /**
   * Get raw data.
   *
   * @return array|null
   *   The raw data.
   */
  public function getRaw(): ?array;

  /**
   * Set raw data.
   *
   * @param array|null $raw
   *   The raw data.
   */
  public function setRaw(?array $raw): void;

  /**
   * Get text.
   *
   * @return string
   *   The text.
   */
  public function getText(): string;

  /**
   * Set text.
   *
   * @param string $text
   *   The text.
   */
  public function setText(string $text): void;

  /**
   * Get metadata.
   *
   * @return array
   *   The metadata.
   */
  public function getMetadata(): array;

  /**
   * Set metadata.
   *
   * @param array $metadata
   *   The metadata.
   */
  public function setMetadata(array $metadata): void;

  /**
   * Set the total tokens used by the AI provider.
   *
   * @param int $tokens
   *   The amount of tokens.
   */
  public function setTotalTokenUsage(int $tokens): void;

  /**
   * Set the input tokens used by the AI provider.
   *
   * @param int $tokens
   *   The amount of tokens.
   */
  public function setInputTokenUsage(int $tokens): void;

  /**
   * Set the output tokens used by the AI provider.
   *
   * @param int $tokens
   *   The amount of tokens.
   */
  public function setOutputTokenUsage(int $tokens): void;

  /**
   * Set the reasoning tokens used by the AI provider.
   *
   * @param int $tokens
   *   The amount of tokens.
   */
  public function setReasoningTokenUsage(int $tokens): void;

  /**
   * Set the cached tokens used by the AI provider.
   *
   * @param int $tokens
   *   The amount of tokens.
   */
  public function setCachedTokenUsage(int $tokens): void;

  /**
   * Gets the total tokens used by the AI provider.
   *
   * @return int|null
   *   The total token usage.
   */
  public function getTotalTokenUsage(): ?int;

  /**
   * Gets the input tokens used by the AI provider.
   *
   * @return int|null
   *   The input token usage.
   */
  public function getInputTokenUsage(): ?int;

  /**
   * Gets the output tokens used by the AI provider.
   *
   * @return int|null
   *   The output token usage.
   */
  public function getOutputTokenUsage(): ?int;

  /**
   * Gets the reasoning tokens used by the AI provider.
   *
   * @return int|null
   *   The reasoning token usage.
   */
  public function getReasoningTokenUsage(): ?int;

  /**
   * Gets the cached tokens used by the AI provider.
   *
   * @return int|null
   *   The cached token usage.
   */
  public function getCachedTokenUsage(): ?int;

}
