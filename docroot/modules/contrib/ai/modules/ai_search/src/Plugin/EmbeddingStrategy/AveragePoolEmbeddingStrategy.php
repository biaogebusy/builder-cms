<?php

namespace Drupal\ai_search\Plugin\EmbeddingStrategy;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_search\Attribute\EmbeddingStrategy;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\Item\ItemInterface;

/**
 * Plugin implementation of the enriched composite embedding strategy.
 *
 * This strategy adds contextual content to the chunked body fields, embeds the
 * chunks, and then uses Average Pooling to obtain a single composite vector.
 * This avoids multiple chunks for the same content item which can improve
 * performance at the cost of accuracy of results.
 */
#[EmbeddingStrategy(
  id: 'average_pool',
  label: new TranslatableMarkup('Enriched Composite Embedding'),
  description: new TranslatableMarkup('This generates a single vector representation of all chunks by averaging the chunks.'),
)]
class AveragePoolEmbeddingStrategy extends EmbeddingBase {

  /**
   * {@inheritDoc}
   */
  public function getEmbedding(
    string $embedding_engine,
    string $chat_model,
    array $configuration,
    array $fields,
    ItemInterface $search_api_item,
    IndexInterface $index,
  ): array {
    $this->init($embedding_engine, $chat_model, $configuration);
    [$title, $contextual_content, $main_content] = $this->groupFieldData($fields, $index);
    $chunks = $this->getChunks($title, $main_content, $contextual_content);

    // Embed and average.
    if ($raw_embeddings = $this->getRawEmbeddings($chunks)) {
      $embedding = $this->averagePooling($raw_embeddings);
      $content = $title . $main_content . $contextual_content;
      $metadata = $this->buildBaseMetadata($fields, $index);
      $metadata = $this->addContentToMetadata($metadata, $content, $index);

      // Build the result, optionally adding metadata.
      $results = [
        'id' => $search_api_item->getId() . ':0',
        'values' => $embedding,
        'metadata' => $metadata,
      ];
      return [$results];
    }
    return [];
  }

  /**
   * Return merged embedding via Average Pooling.
   *
   * @param array $embeddings
   *   The embeddings.
   *
   * @return array
   *   The updated average embeddings.
   */
  protected function averagePooling(array $embeddings): array {
    $numEmbeddings = count($embeddings);
    $embeddingSize = count($embeddings[0]);

    $averageEmbedding = array_fill(0, $embeddingSize, 0.0);

    foreach ($embeddings as $embedding) {
      for ($i = 0; $i < $embeddingSize; $i++) {
        $averageEmbedding[$i] += $embedding[$i];
      }
    }

    for ($i = 0; $i < $embeddingSize; $i++) {
      $averageEmbedding[$i] /= $numEmbeddings;
    }

    return $averageEmbedding;
  }

}
