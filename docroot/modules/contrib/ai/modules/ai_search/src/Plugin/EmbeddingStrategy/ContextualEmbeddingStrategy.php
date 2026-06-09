<?php

namespace Drupal\ai_search\Plugin\EmbeddingStrategy;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai_search\Attribute\EmbeddingStrategy;

/**
 * Plugin implementation of the enriched embedding strategy.
 *
 * This strategy adds contextual information to each chunk of main content. This
 * is the recommended strategy for most use cases.
 */
#[EmbeddingStrategy(
  id: 'contextual_chunks',
  label: new TranslatableMarkup('Enriched Embedding Strategy'),
  description: new TranslatableMarkup('This generates multiple vector representations of the content enriched with repeated contextual information alongside each chunk.'),
)]
class ContextualEmbeddingStrategy extends EmbeddingBase {

}
