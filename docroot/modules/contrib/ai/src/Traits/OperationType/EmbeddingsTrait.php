<?php

namespace Drupal\ai\Traits\OperationType;

use Drupal\ai\OperationType\Embeddings\EmbeddingsInput;

/**
 * Chat specific base methods.
 *
 * @package Drupal\ai\Traits\OperationType
 */
trait EmbeddingsTrait {

  /**
   * {@inheritdoc}
   */
  public function maxEmbeddingsInput(string $model_id = ''): int {
    return 1024;
  }

  /**
   * {@inheritdoc}
   */
  public function embeddingsVectorSize(string $model_id): int {
    $cache = \Drupal::cache('ai');
    $cid = 'embeddings_size:' . $this->getPluginId() . ':' . $model_id;
    if ($cached = $cache->get($cid)) {
      return $cached->data;
    }

    // Just until all providers have the trait.
    if (!method_exists($this, 'embeddings')) {
      return 0;
    }
    // Normalize the input.
    $input = new EmbeddingsInput('Hello world!');
    $embedding = $this->embeddings($input, $model_id);
    try {
      $size = count($embedding->getNormalized());
    }
    catch (\Exception $e) {
      return 0;
    }
    $cache->set($cid, $size);

    return $size;
  }

}
