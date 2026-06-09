<?php

namespace Drupal\ai\Enum;

/**
 * Enum of Embedding Strategy capabilities when not shared across all.
 */
enum EmbeddingStrategyCapability: string {
  case MultipleMainContent = 'Supports multiple "Main Content" fields for indexing';
}
