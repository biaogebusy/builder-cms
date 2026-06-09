<?php

declare(strict_types=1);

namespace Drupal\ai\Enum;

/**
 * A flag for defining distance metrics being used.
 */
enum VdbSimilarityMetrics: string {
  case CosineSimilarity = 'cosine_similarity';
  case EuclideanDistance = 'euclidean_distance';
  case InnerProduct = 'inner_product';
}
