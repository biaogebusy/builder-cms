<?php

/*
 * Copyright (c) 2003-2026, CKSource Holding sp. z o.o. All rights reserved.
 * For licensing, see https://ckeditor.com/legal/ckeditor-oss-license
 */

declare(strict_types=1);

namespace Drupal\ckeditor5_premium_features\Utility;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;

/**
 * Helper class for getting the editor toolbar plugins.
 */
class PluginHelper {

  /**
   * Returns an array of enabled toolbar plugins names.
   *
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Processed form state object.
   *
   * @return array
   *   A list of toolbar plugins.
   */
  public function getFormToolbars(FormStateInterface $form_state) :array {
    $complete_form_state = $form_state->getCompleteFormState();
    $values = $complete_form_state->cleanValues()->getValues();

    $toolbars_raw = (string) NestedArray::getValue($values, [
      'editor',
      'settings',
      'toolbar',
      'items',
    ]);

    return (array) json_decode($toolbars_raw);
  }

}
