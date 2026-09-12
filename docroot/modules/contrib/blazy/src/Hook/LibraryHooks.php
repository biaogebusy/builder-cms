<?php

namespace Drupal\blazy\Hook;

use Drupal\blazy\BlazyDefault;
use Drupal\blazy\Internals\Internals;

/**
 * Hook implementations for library.
 *
 * @todo add #[Hook] attribute, and change them to instance class for D11.
 */
class LibraryHooks {

  /**
   * The blazy library info.
   *
   * @var array|null
   */
  protected static $libraryInfoBuild;

  /**
   * Implements hook_library_info_alter().
   *
   * {@inheritdoc}
   */
  public static function libraryInfoAlter(&$libraries, $extension): void {
    if (!self::isAlterable($libraries, $extension)) {
      return;
    }

    static $bajax;

    // @todo deprecate and remove if core changed, right below core/drupal for being generic,
    // and dependency-free and a dependency for many other generic ones.
    // @todo watch out for core @todo to remove drupal namespace for debounce.
    $debounce = 'drupal.debounce';
    if ($extension === 'core') {
      if (isset($libraries[$debounce])) {
        $libraries[$debounce]['js']['misc/debounce.js'] = ['weight' => -16];
      }
      if (!isset($bajax) && isset($libraries['drupal.ajax'])) {
        $bajax = TRUE;
      }
    }

    if ($extension === 'media' && isset($libraries['oembed.frame'])) {
      $libraries['oembed.frame']['dependencies'][] = 'blazy/oembed';
    }

    // Blazy colorbox needs these higher.
    foreach (BlazyDefault::thirdPartyLibraries() as $module => $libs) {
      if ($extension === $module) {
        foreach ($libs as $id => $lib) {
          if (isset($libraries[$id]) && $js = $lib['js']) {
            $libraries[$id]['js'][$js]['weight'] = $lib['weight'];

            // See https://stackoverflow.com/questions/10808109
            if ($attributes = $lib['attributes'] ?? []) {
              $libraries[$id]['js'][$js]['attributes'] = $attributes;
            }
          }
        }
      }
    }

    if ($extension === 'blazy') {
      $names = ['DOMPurify', 'dompurify'];
      $manager = Internals::blazy();
      $path = $manager ? $manager->getLibrariesPath($names) : NULL;
      if ($path) {
        $js = [
          '/' . $path . '/dist/purify.min.js' => [
            'minified' => TRUE,
            'weight' => -16,
          ],
        ];
        $libraries['dompurify']['js'] = $js;
        $libraries['dblazy']['dependencies'][] = 'blazy/dompurify';
      }

      // Add blazy/bio.ajax only if both core drupal.ajax and blazy exist.
      if (isset($bajax) && isset($libraries['load'])) {
        $libraries['load']['dependencies'][] = 'blazy/bio.ajax';
      }
    }
  }

  /**
   * Implements hook_library_info_build().
   *
   * {@inheritdoc}
   */
  public static function libraryInfoBuild() {
    if (!isset(static::$libraryInfoBuild)) {
      $libraries = [];
      // Optional polyfills for IEs, and oldies.
      $polyfills = array_merge(BlazyDefault::polyfills(), BlazyDefault::ondemandPolyfills());
      foreach ($polyfills as $id) {
        // Matches common core polyfills' weight.
        $weight = $id == 'polyfill' ? -21 : -20;
        $weight = $id == 'webp' ? -5.5 : $weight;
        $common = ['minified' => TRUE, 'weight' => $weight];
        $libraries[$id] = [
          'js' => [
            'js/polyfill/blazy.' . $id . '.min.js' => $common,
          ],
        ];

        if ($id == 'webp') {
          $libraries[$id]['dependencies'][] = 'blazy/dblazy';
        }
      }

      // Plugins extending dBlazy.
      foreach (BlazyDefault::plugins() as $id) {
        $base = ['eventify', 'viewport', 'dataset'];
        $base = in_array($id, $base);
        $deps = $base ? ['blazy/dblazy', 'blazy/base'] : ['blazy/xlazy'];
        if ($id == 'xlazy') {
          $deps = ['blazy/viewport', 'blazy/dataset'];
        }

        // @todo problematic weight, basically compat must be present.
        if (in_array($id, ['animate', 'background'])) {
          $deps[] = 'blazy/compat';
        }
        $weight = $base ? -5.6 : -5.5;

        $common = ['minified' => TRUE, 'weight' => $weight];
        $libraries[$id] = [
          'js' => [
            'js/plugin/blazy.' . $id . '.min.js' => $common,
          ],
          'dependencies' => $deps,
        ];
      }

      // Components, normally non-generic, unlike plugins.
      foreach (BlazyDefault::dyComponents() as $id => $component) {
        $libraries[$id] = $component;
      }

      static::$libraryInfoBuild = $libraries;
    }
    return static::$libraryInfoBuild;
  }

  /**
   * Checks if we need to alter the library.
   */
  private static function isAlterable(array &$libraries, $extension): bool {
    $core = $extension === 'core' && isset($libraries['drupal.debounce']);
    $check = in_array($extension, [
      'blazy',
      'media',
      'media_entity_instagram',
      'media_entity_pinterest',
    ]);
    return $check || $core;
  }

}
