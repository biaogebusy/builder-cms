<?php

namespace Drupal\gin;

use Drupal\Core\Security\TrustedCallbackInterface;

/**
 * Implements trusted prerender callbacks for the Gin theme.
 *
 * @internal
 */
class GinPreRender implements TrustedCallbackInterface {

  /**
   * Prepare description toggle for output in template.
   */
  public static function textFormat($element) {

    if (\Drupal::classResolver(GinDescriptionToggle::class)->isEnabled() && !empty($element['#description'])) {
      if ($element['#type'] === 'text_format') {
        $element['value']['#description_toggle'] = TRUE;
      }
      else {
        $element['#description_toggle'] = TRUE;
        $element['#description_display'] = 'invisible';
      }

    }
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function trustedCallbacks() {
    return [
      'textFormat',
    ];
  }

}
