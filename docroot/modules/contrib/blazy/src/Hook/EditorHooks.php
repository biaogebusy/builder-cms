<?php

namespace Drupal\blazy\Hook;

use Drupal\blazy\Internals\Internals;
use Drupal\editor\Entity\Editor;

/**
 * Hook implementations for editor.
 *
 * @todo add #[Hook] attribute, and change them to instance class for D11.
 */
class EditorHooks {

  /**
   * Implements hook_ckeditor_css_alter().
   *
   * {@inheritdoc}
   */
  public static function ckeditorCssAlter(array &$css, Editor $editor): void {
    if (self::isCkeditorApplicable($editor)) {
      $path = Internals::getPath('module', 'blazy', TRUE);
      $css[] = $path . '/css/components/blazy.media.css';
      $css[] = $path . '/css/components/blazy.preview.css';
      $css[] = $path . '/css/components/blazy.ratio.css';
    }
  }

  /**
   * Checks if Entity/Media Embed is enabled.
   *
   * {@inheritdoc}
   */
  private static function isCkeditorApplicable(Editor $editor): bool {
    foreach (['entity_embed', 'media_embed'] as $filter) {
      if (!$editor->isNew()
        && $editor->getFilterFormat()->filters()->has($filter)
        && $editor->getFilterFormat()
          ->filters($filter)
          ->getConfiguration()['status']) {
        return TRUE;
      }
    }
    return FALSE;
  }

}
