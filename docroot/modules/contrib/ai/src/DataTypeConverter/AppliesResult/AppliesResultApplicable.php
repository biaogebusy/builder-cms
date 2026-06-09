<?php

namespace Drupal\ai\DataTypeConverter\AppliesResult;

use Drupal\ai\DataTypeConverter\AppliesResult;

/**
 * Applicable result.
 *
 * Represents an applicable result.
 */
class AppliesResultApplicable extends AppliesResult {

  /**
   * {@inheritdoc}
   */
  public function applies(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function valid(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getReason(): string {
    return 'Context applies and is valid.';
  }

}
