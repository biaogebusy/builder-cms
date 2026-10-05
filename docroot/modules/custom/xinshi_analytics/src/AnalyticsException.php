<?php

declare(strict_types=1);

namespace Drupal\xinshi_analytics;

/** Public failures never carry database, field or credential details. */
final class AnalyticsException extends \RuntimeException {

  /** Constructs an error using the count-tool protocol vocabulary. */
  public function __construct(string $code) {
    if (!in_array($code, ['invalid_query', 'dataset_unavailable', 'dataset_version_changed',
      'range_too_large', 'query_failed', 'evidence_unavailable'], TRUE)) {
      throw new \InvalidArgumentException('Unknown analytics error code.');
    }
    parent::__construct($code);
  }

}

