<?php

namespace Drupal\blazy\Internals;

use Drupal\blazy\BlazyApi;
use Drupal\blazy\BlazyDefault;

/**
 * Provides feature check methods at container level, or globally.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module. Please use the public method instead.
 */
final class Container {

  /**
   * Checks for container stuffs, mostly re-definition in case set earlier.
   *
   * @param array $settings
   *   The settings being modified.
   *
   * @todo deprecate and remove some settings after sub-modules.
   */
  public static function check(array &$settings): void {
    $blazies      = Internals::getBlazies($settings);
    $item_id      = $blazies->get('item.id', 'blazy');
    $item_caption = $blazies->get('item.caption', 'captions');
    $item_prefix  = $blazies->get('item.prefix', 'blazy');
    $namespace    = $blazies->get('namespace', $settings['namespace'] ?? 'blazy');

    self::checkUi($settings);

    // Some should be refined per item against potential mixed media items.
    // @todo move some into ::prepare() as might be called per item.
    $stage = $settings['image'] ?? NULL;
    $stage = $blazies->get('field.formatter.image', $stage);
    $blazies->set('is.hires', !empty($stage))
      ->set('item.id', $item_id)
      ->set('item.caption', $item_caption)
      ->set('item.prefix', $item_prefix)
      ->set('namespace', $namespace)
      ->set('was.container', TRUE);
  }

  /**
   * Checks for container defined by UI, where Blazy is not the formatter.
   *
   * Mostly for third party settings, using the global UI settings.
   *
   * @param array $settings
   *   The settings being modified.
   */
  public static function checkUi(array &$settings): void {
    $blazies      = Internals::getBlazies($settings);
    $ui           = $blazies->get('ui');
    $bundles      = $blazies->get('field.target_bundles', []);
    $medias       = $blazies->get('media.defaults', BlazyDefault::mediaDefaults());
    $ratios       = $blazies->get('css.ratio', BlazyDefault::RATIO);
    $is_audio     = $bundles && in_array('audio', $bundles);
    $is_video     = $bundles && in_array('video', $bundles);
    $_loading     = $settings['loading'] ?? '';
    $loading      = $settings['loading'] = $_loading ?: 'lazy';
    $is_preview   = Path::isPreview();
    $is_amp       = Path::isAmp();
    $is_sandboxed = Path::isSandboxed();
    $is_bg        = !empty($settings['background']);
    $is_unload    = !empty($ui['nojs']['lazy']);
    $is_slider    = $loading == 'slider';
    $is_unloading = $loading == 'unlazy';
    $is_defer     = $loading == 'defer';
    $is_fluid     = ($settings['ratio'] ?? '') == 'fluid';
    $is_static    = $is_preview || $is_amp || $is_sandboxed;
    $is_undata    = $is_static || $is_unloading;
    $is_nojs      = $is_unload || $is_static;

    /* @phpstan-ignore-next-line */
    $is_resimage = is_callable('responsive_image_get_mime_type');
    $is_resimage = $blazies->is('resimage', $is_resimage);
    $svg_exist   = BlazyApi::svgSanitizerExists();

    // When `defer` is chosen, overrides global `No JavaScript: lazy`, ensures
    // to not affect AMP, CKEditor, or other preview pages where nojs is a must.
    if ($is_nojs && $is_defer) {
      $is_nojs = $is_undata;
    }

    // Compat is anything that Native lazy doesn't support.
    $is_compat = $is_bg
      || $is_fluid
      || $is_audio
      || $is_video
      || $is_defer
      || $blazies->get('fx')
      || $blazies->get('libs.compat');

    // For Hero media specific to blazy formatters.
    // Only if not set, set Hero delta here, see BlazyLayoutBase.
    if ($is_unloading && !$blazies->was('initial')) {
      $blazies->set('initial', 0);
    }

    // Some should be refined per item against potential mixed media items.
    // @todo move some into ::prepare() as might be called per item.
    // @todo deprecate and remove some overlaps is for use.
    $blazies->set('css.ratio', $ratios, TRUE)
      ->set('image.loading', $loading)
      ->set('is.amp', $is_amp)
      ->set('is.blazy', TRUE)
      ->set('is.fluid', $is_fluid)
      ->set('is.nojs', $is_nojs)
      ->set('is.preview', $is_preview)
      ->set('is.privacy_consent', !empty($ui['privacy_consent']))
      ->set('is.resimage', $is_resimage)
      ->set('is.sandboxed', $is_sandboxed)
      ->set('is.slider', $is_slider)
      ->set('is.static', $is_static)
      ->set('is.svg_sanitizer', $svg_exist)
      ->set('is.undata', $is_undata)
      ->set('is.unload', $is_unload)
      ->set('is.unloading', $is_unloading)
      ->set('is.unlazy', $is_nojs)
      ->set('lazy.html', !empty($ui['lazy_html']))
      ->set('libs.background', $is_bg || $is_audio)
      ->set('libs.compat', $is_compat || $is_bg)
      ->set('libs.ratio', !empty($settings['ratio']))
      ->set('media.defaults', $medias)
      ->set('use.bg', $is_bg)
      ->set('use.dataset', $is_bg || $is_video)
      ->set('use.encodedbox', !empty($ui['use_encodedbox']))
      ->set('use.image', TRUE)
      ->set('use.loader', !$is_nojs)
      ->set('use.script', FALSE)
      ->set('use.svg_dimensions', TRUE);
  }

}
