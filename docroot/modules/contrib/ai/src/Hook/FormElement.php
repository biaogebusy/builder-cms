<?php

namespace Drupal\ai\Hook;

use Drupal\Core\Hook\Attribute\Hook;

/**
 * Contain hooks for form elements.
 */
class FormElement {

  /**
   * Implements hook_element_info_alter().
   */
  #[Hook('element_info_alter')]
  public function elementInfoAlter(array &$info) {
    if (isset($info['textarea'])) {
      $info['textarea']['#process'][] = [static::class, 'addMdxEditor'];
    }
  }

  /**
   * Adds the MDXEditor library.
   */
  public static function addMdxEditor($element): array {
    if (!empty($element['#attributes']) && array_key_exists('data-mdxeditor', $element['#attributes'])) {
      $element['#attached']['library'][] = 'ai/mdx_editor';
    }
    return $element;
  }

}
