<?php

namespace Drupal\ai\DataTypeConverter\AppliesResult;

use Drupal\ai\DataTypeConverter\AppliesResult;

/**
 * Not Applicable result.
 *
 * Represents a result that is not applicable.
 */
class AppliesResultNotApplicable extends AppliesResult {

  /**
   * {@inheritdoc}
   */
  public function applies(): bool {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function valid(): bool {
    return FALSE;
  }

}
