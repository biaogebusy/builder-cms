<?php

namespace Drupal\pathauto_custom_punctuation_test\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Hook implementations for pathauto_custom_punctuation_test.
 */
class PathautoCustomPunctuationTestHooks {

  use StringTranslationTrait;

  /**
   * Implements hook_pathauto_punctuation_chars_alter().
   */
  #[Hook('pathauto_punctuation_chars_alter')]
  public function pathautoPunctuationCharsAlter(array &$punctuation): void {
    $punctuation['copyright'] = [
      'value' => '©',
      'name' => $this->t('Copyright symbol'),
    ];
  }

}
