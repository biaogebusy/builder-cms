<?php

/**
 * @file
 * API documentation for the Image Effects module.
 */

use Drupal\image\ConfigurableImageEffectBase;

/**
 * Alter the text of a Text Overlay effect before overlaying on the image.
 *
 * @param string $text
 *   The text string to be altered.
 * @param \Drupal\image\ConfigurableImageEffectBase $image_effect
 *   The Text Overlay image effect plugin for which text need to be altered.
 */
function hook_image_effects_text_overlay_text_alter(&$text, ConfigurableImageEffectBase $image_effect) {
  // Skip if the effect is not TextOverlayImageEffect or an alternative
  // implementation.
  if ($image_effect->getPluginId() !== "image_effects_text_overlay") {
    return;
  }
  $text = 'my altered text';
}
