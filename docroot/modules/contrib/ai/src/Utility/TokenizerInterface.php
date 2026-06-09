<?php

namespace Drupal\ai\Utility;

/**
 * The tokenizer wrapper interface.
 */
interface TokenizerInterface {

  /**
   * Sets the text chunker model.
   *
   * @param string $model
   *   The model to encode tokens for.
   */
  public function setModel(string $model): void;

  /**
   * Get the chat model options that the tokenizer supports.
   *
   * @return array
   *   The chat model options that Tokenizer supports.
   */
  public function getSupportedModels(): array;

  /**
   * Get the tokens for a given string.
   *
   * @param string $chunk
   *   The chunk to encode.
   *
   * @return array
   *   The tokens.
   */
  public function getTokens(string $chunk): array;

  /**
   * Get the number of tokens for an encoded string.
   *
   * @param string $chunk
   *   The chunk to encode.
   *
   * @return int
   *   The number of tokens.
   */
  public function countTokens(string $chunk): int;

}
