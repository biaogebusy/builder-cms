<?php

namespace Drupal\ai\OperationType\Embeddings;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\OperationType;
use Drupal\ai\OperationType\OperationTypeInterface;

/**
 * Interface for embeddings models.
 */
#[OperationType(
  id: 'embeddings',
  label: new TranslatableMarkup('Embeddings'),
)]
interface EmbeddingsInterface extends OperationTypeInterface {

  /**
   * Generate embeddings.
   *
   * @param string|\Drupal\ai\OperationType\Embeddings\EmbeddingsInput $input
   *   The prompt or the embeddings input.
   * @param string $model_id
   *   The model id to use.
   * @param array $tags
   *   Extra tags to set.
   *
   * @return \Drupal\ai\OperationType\Embeddings\EmbeddingsOutput
   *   The embeddings output.
   */
  public function embeddings(string|EmbeddingsInput $input, string $model_id, array $tags = []): EmbeddingsOutput;

  /**
   * Max input string length for Embedding LLM.
   *
   * @param string $model_id
   *   The model id to use.
   *
   * @return int
   *   Max input string length in bytes.
   */
  public function maxEmbeddingsInput(string $model_id = ''): int;

  /**
   * Embedding vector size.
   *
   * @param string $model_id
   *   The model id to use.
   *
   * @return int
   *   Max input string length in bytes.
   */
  public function embeddingsVectorSize(string $model_id): int;

}
