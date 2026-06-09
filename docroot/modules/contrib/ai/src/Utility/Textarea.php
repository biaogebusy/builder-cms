<?php

namespace Drupal\ai\Utility;

use Drupal\Core\Form\FormStateInterface;

/**
 * Utility class for textarea form element overrides.
 */
class Textarea {

  /**
   * {@inheritdoc}
   */
  public static function valueCallback(&$element, $input, FormStateInterface $form_state) {
    if ($input !== FALSE && $input !== NULL) {
      // This should be a string, but allow other scalars since they might be
      // valid input in programmatic form submissions.
      $value = is_scalar($input) ? (string) $input : '';
      // Normalize all newline strings. This allows filters to only deal with
      // one possibility.
      return $element['#normalize_newlines'] ? str_replace(["\r\n", "\r"], "\n", $value) : $value;
    }
    return NULL;
  }

}
