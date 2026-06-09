<?php

namespace Drupal\ai\DataTypeConverter\AppliesResult;

use Drupal\ai\DataTypeConverter\AppliesResult;

/**
 * Invalid Applicable result.
 *
 * The result of a check when the converter may apply to a context but whose
 * value is invalid and inapplicable in its current form.
 */
class AppliesResultInvalid extends AppliesResult {

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
    return FALSE;
  }

}
