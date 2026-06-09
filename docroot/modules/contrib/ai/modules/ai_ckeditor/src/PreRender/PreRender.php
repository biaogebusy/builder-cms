<?php

namespace Drupal\ai_ckeditor\PreRender;

use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * Adds the sourceEditing plugin to AI response text_format fields.
 */
class PreRender implements TrustedCallbackInterface {

  /**
   * {@inheritDoc}
   */
  public static function trustedCallbacks(): array {
    return ['textFormatPreRender'];
  }

  /**
   * Force sourceEditing on the AI Generation form.
   *
   * @param array<string, mixed> $element
   *   The text_format field element.
   *
   * @return array<string, mixed>
   *   The element.
   */
  public static function textFormatPreRender(array $element): array {
    if (isset($element['#ai_ckeditor_response'])) {

      // If this is our AI Response field, we want to force the sourceEditing
      // plugin to be enabled as the code will not work without it.
      if (isset($element['#attached']['drupalSettings']['editor']['formats'])) {
        foreach ($element['#attached']['drupalSettings']['editor']['formats'] as &$settings) {
          if (isset($settings['editorSettings']['toolbar']['items'])) {
            if (!in_array('sourceEditing', $settings['editorSettings']['toolbar']['items'])) {
              $settings['editorSettings']['toolbar']['items'][] = 'sourceEditing';
              $settings['editorSettings']['plugins'][] = 'sourceEditing.SourceEditing';
              $settings['editorSettings']['plugins'][] = 'htmlSupport.GeneralHtmlSupport';
              $element['#attached']['library'][] = 'core/ckeditor5.sourceEditing';
            }
          }
        }
      }
    }

    return $element;
  }

}
