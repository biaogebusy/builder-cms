<?php

/**
 * @file
 * Webform libraries.
 */

use Drupal\Core\Hook\Attribute\LegacyHook;
use Drupal\webform\Hook\WebformLibrariesHooks;
use Drupal\Core\Asset\AttachedAssetsInterface;

/**
 * Implements hook_library_info_build().
 */
#[LegacyHook]
function webform_library_info_build() {
  return \Drupal::service(WebformLibrariesHooks::class)->libraryInfoBuild();
}

/**
 * Implements hook_library_info_alter().
 */
#[LegacyHook]
function webform_library_info_alter(&$libraries, $extension) {
  return \Drupal::service(WebformLibrariesHooks::class)->libraryInfoAlter($libraries, $extension);
}

/**
 * Recursive through a webform library.
 *
 * @param array $library
 *   A webform library defined in webform.libraries.yml.
 * @param array $cdn
 *   A associative array of library paths mapped to CDN URL.
 */
function _webform_library_info_alter_recursive(array &$library, array $cdn) {
  foreach ($library as $key => &$value) {
    // CSS and JS files and listed in associative arrays keyed via string.
    if (!is_string($key) || !is_array($value)) {
      continue;
    }

    // Ignore the CDN's associative array.
    if ($key === 'cdn') {
      continue;
    }

    // Replace the CDN sources (i.e. /library/*) with the CDN URL destination
    // (https://cdnjs.cloudflare.com/ajax/libs/*).
    foreach ($cdn as $source => $destination) {
      if (str_starts_with($key, $source)) {
        $uri = str_replace($source, $destination, $key);
        $library[$uri] = $value;
        $library[$uri]['type'] = 'external';
        unset($library[$key]);
        break;
      }
    }

    // Recurse downward to find nested libraries.
    _webform_library_info_alter_recursive($value, $cdn);
  }
}

/**
 * Implements hook_css_alter().
 */
#[LegacyHook]
function webform_css_alter(&$css, AttachedAssetsInterface $assets) {
  \Drupal::service(WebformLibrariesHooks::class)->cssAlter($css, $assets);
}

/**
 * Implements hook_js_alter().
 */
#[LegacyHook]
function webform_js_alter(&$javascript, AttachedAssetsInterface $assets) {
  \Drupal::service(WebformLibrariesHooks::class)->jsAlter($javascript, $assets);
}

/**
 * Alter Webform CSS or JavaScript assets and make sure they appear last.
 *
 * @param array $items
 *   An array of all CSS or JavaScript being presented on the page.
 * @param string $type
 *   The type of asset being attached.
 *
 * @see hook_library_info_build()
 */
function _webform_asset_alter(array &$items, $type) {
  foreach ($items as $key => &$item) {
    if (str_starts_with($key, "/webform/$type/")) {
      $item['weight'] = 1000;
      $item['group'] = 1000;
    }
  }
}
