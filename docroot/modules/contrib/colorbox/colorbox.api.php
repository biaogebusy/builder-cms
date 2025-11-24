<?php

/**
 * @file
 * API documentation for the colorbox module.
 */

/**
 * Allows to override Colorbox settings and style.
 *
 * Implements hook_colorbox_settings_alter().
 *
 * @param array $settings
 *   An associative array of Colorbox settings. See the.
 * @param string $style
 *   The name of the active style plugin. If $style is 'none', no Colorbox
 *   theme will be loaded.
 *
 * @link
 *    http://colorpowered.com/colorbox/ Colorbox documentation.
 * @endlink
 *   for the full list of supported parameters.
 *
 * @codingStandardsIgnoreStart
 */
function hook_colorbox_settings_alter(&$settings, &$style) {
  // @codingStandardsIgnoreEnd
  // Disable automatic down scaling of images to maxWidth/maxHeight size.
  $settings['scalePhotos'] = FALSE;

  // Use custom style plugin specifically for node/123.
  if (current_path() == 'node/123') {
    $style = 'mystyle';
  }
}
