<?php

namespace Drupal\webform_test_options\Hook;

use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Hook\Attribute\Hook;

/**
 * Hook implementations for webform_test_options.
 */
class WebformTestOptionsHooks {
  use StringTranslationTrait;

  /**
   * Implements hook_webform_options_alter().
   */
  #[Hook('webform_options_alter')]
  public function webformOptionsAlter(array &$options, array &$element, $id) {
    if ($id === 'custom') {
      $options = ['one' => $this->t('One'), 'two' => $this->t('Two'), 'three' => $this->t('Three')];
      // Set the default value to one of the added options.
      $element['#default_value'] = 'one';
    }
  }

}
