<?php

namespace Drupal\ai\Utility;

/**
 * Utility for chunking text in an NLP friendly manner.
 */
class TextChunker implements TextChunkerInterface {

  /**
   * Construct the text chunker utility.
   *
   * @param \Drupal\ai\Utility\TokenizerInterface $tokenizer
   *   The tokenizer service.
   */
  public function __construct(
    protected TokenizerInterface $tokenizer,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function setModel(string $model): void {
    $this->tokenizer->setModel($model);
  }

  /**
   * {@inheritdoc}
   */
  public function chunkText(string $text, int $maxSize, int $minOverlap): array {
    if (
      !method_exists($this->tokenizer, 'getEncodedChunks')
      || !method_exists($this->tokenizer, 'decodeChunk')
    ) {
      throw new \Exception('This text chunker expects that the encoder has getChunks and decodeChunk methods.');
    }

    // If we have overlap set, the max chunk size should be reduced by the
    // minimum overlap.
    $calculated_max_size = $maxSize - $minOverlap;
    if ($calculated_max_size <= 0) {
      throw new \Exception('The minimum overlap cannot be equal to or exceed the maximum chunk size.');
    }
    $chunks = $this->tokenizer->getEncodedChunks($text, $calculated_max_size);

    // Ensure that chunks are numerical indexed to handle overlap calculation.
    $chunks = array_values($chunks);
    foreach ($chunks as $key => $chunk) {

      // If there is no preceding chunk or no overlap, we can do a simpler
      // chunking. Otherwise we need to consider preceding chunks and
      // calculate the overlap.
      if ($key < 1 || !isset($chunks[$key - 1]) || $minOverlap <= 0) {
        if (is_array($chunk)) {
          $chunks[$key] = trim($this->tokenizer->decodeChunk($chunk));
        }
        continue;
      }

      // Ensure numeric index for later merging to be reliable.
      $chunk = array_values($chunk);
      $preceding_chunk = $chunks[$key - 1];

      // Break the preceding chunk it into even smaller
      // chunks in order to get the tail end of it matching the minimum
      // overlap. If we do not use a smaller size, if overlap is 10 and last
      // chunk is 9, we will end up getting 19 tokens. This reduces how
      // far we exceed the minimum overlap.
      $smaller_chunks_overlap = ceil($minOverlap / 10);
      $preceding_sub_chunks = $this->tokenizer->getEncodedChunks($preceding_chunk, $smaller_chunks_overlap);

      // Now go back up to the amount of the overlap, ensuring a minimum
      // of 1 token to retrieve.
      $preceding_chunks_to_retrieve = min(count($preceding_sub_chunks), $smaller_chunks_overlap * 10);
      $preceding_chunks_to_retrieve = $preceding_chunks_to_retrieve ?: 1;
      $preceding_sub_chunks = array_filter($preceding_sub_chunks);
      $preceding_sub_chunks = array_values($preceding_sub_chunks);

      // Convert the chunk tokens back into strings, prepending the preceding
      // overlap if set.
      $chunks[$key] = '';
      $preceding_tokens = 0;
      $retrieved_preceding_token_chunks = array_slice($preceding_sub_chunks, (-1 * $preceding_chunks_to_retrieve));
      foreach (array_reverse($retrieved_preceding_token_chunks) as $preceding_sub_chunk) {
        if ($preceding_tokens >= $minOverlap) {
          break;
        }
        $preceding_tokens += count($preceding_sub_chunk);
        $chunks[$key] = $this->tokenizer->decodeChunk($preceding_sub_chunk) . $chunks[$key];
      }
      $chunks[$key] .= $this->tokenizer->decodeChunk($chunk);
      $chunks[$key] = trim($chunks[$key]);
    }

    return $chunks;
  }

}
