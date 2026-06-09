<?php

namespace Drupal\ai\Utility;

/**
 * The text chunker interface.
 */
interface TextChunkerInterface {

  /**
   * Sets the text chunker model ID.
   *
   * @param string $model
   *   The model to encode tokens for.
   */
  public function setModel(string $model): void;

  /**
   * Generate chunks from a string.
   *
   * @param string $text
   *   The text to chunk.
   * @param int $maxSize
   *   The maximum size of the chunk.
   * @param int $minOverlap
   *   The minimum overlap between chunks.
   *
   * @return string[]
   *   An array of chunks.
   */
  public function chunkText(string $text, int $maxSize, int $minOverlap): array;

}
