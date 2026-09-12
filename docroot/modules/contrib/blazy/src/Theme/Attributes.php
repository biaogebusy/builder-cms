<?php

namespace Drupal\blazy\Theme;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\blazy\Internals\Internals;
use Drupal\blazy\Media\Image;
use Drupal\blazy\Media\ResponsiveImage;
use Drupal\blazy\Media\Placeholder;
use Drupal\blazy\Media\Ratio;
use Drupal\blazy\Utility\Arrays;
use Drupal\blazy\Internals\Check;

/**
 * Provides non-reusable blazy attribute static methods.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module.
 */
class Attributes {

  /**
   * Provides attachments when not using the provided API.
   *
   * @param array $variables
   *   The variables being modified.
   * @param array $settings
   *   The settings.
   */
  public static function attach(array &$variables, array $settings = []): void {
    if ($manager = Internals::blazy()) {
      $attachments = $manager->attach($settings) ?: [];
      $variables['#attached'] = Arrays::merge($attachments, $variables, '#attached');
    }
  }

  /**
   * Provides container attributes for .blazy container: .field, .view, etc.
   *
   * Relevant for JS lookups, lightbox galleries, also to accommodate
   * block__no_wrapper, views__no_wrapper, etc. with helpful CSS classes, useful
   * for DOM diets.
   *
   * @param array $attributes
   *   The attributes being modified.
   * @param array $settings
   *   The settings.
   */
  public static function container(array &$attributes, array $settings): void {
    /** @var \Drupal\blazy\BlazySettings $blazies */
    $blazies = Internals::verify($settings);

    if ($attrs = $blazies->get('container.attributes', [])) {
      $attributes = Arrays::merge($attributes, $attrs);
    }

    $classes  = (array) ($attributes['class'] ?? []);
    $data     = $blazies->get('data.blazy');
    $switcher = $blazies->get('lightbox.name') ?: ($settings['media_switch'] ?? NULL);

    // Might be by-passed due to minimal settings, or outside the workflow.
    // See \Drupal\blazy\Hook\ViewsHooks::preprocessViewsView().
    if ($switcher && !$blazies->was('lightbox')) {
      Check::lightboxes($settings);
    }

    $lightbox  = $blazies->get('lightbox.name', $switcher);
    $namespace = $blazies->get('namespace', $settings['namespace'] ?? 'blazy');
    $nested    = $blazies->is('grid_nested');

    // Provides data-LIGHTBOX-gallery to not conflict with original modules.
    // Prevents nested grids from having similar lightbox attributes.
    // Nested grids are seen at Slick|Splide nested/ chunked grids carousels.
    if (!$nested) {
      $options = [
        'namespace' => $namespace,
        'lightbox'  => $lightbox,
        'switcher'  => $switcher,
      ];

      // Provides contextual classes relevant to containers: .field, or .view.
      // Sniffs for Views to allow block__no_wrapper, views__no_wrapper, etc.
      if ($extras = self::firstClasses($attributes, $blazies, $options)) {
        $classes = array_merge($classes, $extras);
      }
    }

    // @todo deprecate and remove when nativegrid masonry no longer needs this.
    if ($blazies->is('grid')) {
      $count = $blazies->get('view.count', 0);
      if (!empty($settings['caption']) ||
        ($count > 1 && $blazies->get('view.multifield'))) {
        $classes[] = 'is-b-captioned';
      }
    }

    // @todo deprecate and remove, hardly used as identifier.
    // if ($blazies->use('ajax')) {
    // $classes[] = 'is-b-ajax';
    // }
    // Needed for nested grids as well: blazy blazy--grid b-nativegrid, etc.
    $attributes['class'] = array_merge(['blazy'], $classes);
    $attributes['data-blazy'] = $data && is_array($data)
      ? Json::encode($data)
      : '';
  }

  /**
   * Modifies container attributes with aspect ratio for iframe, image, etc.
   *
   * @param array $variables
   *   The variables being modified.
   */
  public static function finalize(array &$variables): void {
    /** @var array $attributes */
    $attributes = &$variables['attributes'];

    /** @var array $settings */
    $settings = &$variables['settings'];
    $blazies  = Internals::getBlazies($settings);

    // Aspect ratio to fix layout reflow with lazyloaded images responsively.
    // This is outside 'lazy' to allow non-lazyloaded iframe/content use it too.
    $hacks   = Ratio::hack($settings);
    $hack    = $hacks['hack'];
    $ratio   = $hacks['ratio'];
    $padding = $blazies->get('image.ratio');

    // @todo recheck for 0 padding SVG.
    $settings['ratio'] = $ratio && $padding ? str_replace(':', '', $ratio) : '';

    // Fixed aspect ratio is taken care of by pure CSS. Fluid means dynamic.
    // Unless the computed result above is supported by current CSS rules.
    if ($hack && $padding) {
      // If "lucky", Blazy/ Slick Views galleries may already set this once.
      // Lucky when you don't flatten out the Views output earlier.
      self::inlineStyle($attributes, 'padding-bottom: ' . $padding . '%;');

      // Views rewrite results or Twig inline_template may strip out `style`
      // attributes, provide hint to JS.
      $attributes[self::data($blazies, 'ratio')] = $padding;
    }

    // Since 2.17, lazy load HTML content if so-configured.
    if ($blazies->get('lazy.html')) {
      $unlazy = $blazies->is('static');

      if (!$unlazy && $html = $blazies->get('media.encoded.content')) {
        if (!$blazies->get('bgs')) {
          $attributes['data-src'] = '';
        }

        $attributes['data-b-html'] = Internals::DATA_TEXT . $html;
        $attributes['class'][] = 'b-lazy';

        // @fixme recheck against lightbox whether to load it in supported
        // lightboxes or direct render here.
        $attributes['class'][] = 'b-html';

        // @todo recheck and remove, already checked upstream.
        $blazies->set('is.player', FALSE)
          ->set('use.player', FALSE);

        $settings['media_switch'] = '';
      }
    }

    if ($token = $blazies->get('media.token')) {
      $attributes['data-b-token'] = $token;
    }

    // @todo deprecate and remove BC at 3.x:
    $player = $blazies->use('player') || $blazies->is('player');
    $blazies->set('use.player', $player);

    self::finalizeAnyway($variables, $attributes, $settings);
  }

  /**
   * Provides the media container classes.
   *
   * @param array $variables
   *   The variables being modified.
   * @param array $attributes
   *   The attributes being modified.
   * @param array $settings
   *   The settings being modified.
   */
  public static function finalizeAnyway(array &$variables, array &$attributes, array $settings): void {
    $blazies  = Internals::getBlazies($settings);
    $provider = $blazies->get('media.provider');

    if ($provider == 'local') {
      $blazies->set('media.provider', NULL);
    }

    if ($attrs = $blazies->get('media.attributes', [])) {
      $attributes = Arrays::merge($attributes, $attrs);
    }

    // Makes a little BEM order here due to Twig ignoring the preset priority.
    $classes = (array) ($attributes['class'] ?? []);
    $attributes['class'] = array_merge(['media', 'media--blazy'], $classes);
    $variables['blazies'] = $blazies->storage();
  }

  /**
   * Modifies variables for iframes, those only handled by theme_blazy().
   *
   * This iframe is not printed when `Image to iframe` is chosen.
   *
   * Prepares a media player, and allows a tiny video preview without iframe.
   * image : If iframe switch disabled, fallback to iframe, remove image.
   * player: If no lightboxes, it is an image to iframe switcher.
   * data- : Gets consistent with lightboxes to share JS manipulation.
   *
   * @param array $variables
   *   The variables being modified.
   */
  public static function buildIframe(array &$variables): void {
    /** @var array $settings */
    $settings = &$variables['settings'];
    $blazies  = Internals::getBlazies($settings);

    // Only provide iframe if not for lightboxes, identified by URL.
    if (empty($variables['url'])) {
      // Also empty the image to not get in the way, unless player enabled.
      $variables['image'] = empty($settings['media_switch']) ? [] : $variables['image'];

      // Pass iframe attributes to template, except for Instagram oEmbed, etc.
      // Scripted iframe is like Instagram BLOCKQUOTE js-converted into IFRAME.
      if (!$blazies->use('scripted_iframe')) {
        $variables['iframe'] = Internals::toHtml(NULL, 'iframe', self::iframe($settings));
      }

      // If not media player, iframe only, without image, disable blur.
      if (empty($variables['image']) && isset($variables['preface']['blur'])) {
        $variables['preface']['blur'] = [];
      }
    }
  }

  /**
   * Modifies variables for image and iframe.
   *
   * @param array $variables
   *   The variables being modified.
   */
  public static function buildMedia(array &$variables): void {
    /** @var array $settings */
    $settings = &$variables['settings'];

    /** @var array $attributes */
    $attributes  = &$variables['attributes'];
    $blazies     = Internals::getBlazies($settings);
    $local_video = $blazies->is('video_file') && !$blazies->is('lightbox');
    $bgs         = [];

    // Disable fancy features for local video.
    if ($local_video) {
      $blazies->set('fx', NULL)
        ->set('is.blur', FALSE);
    }

    // 1. Prepares thumbnail and optional placeholder based on thumbnail.
    // Do not place this any lower, else breaking some logic below.
    // The only required for local video is thumbnail for sliders, etc.
    Placeholder::prepare($attributes, $settings);

    // Skip local video, already has usable poster attribute.
    if (!$local_video) {
      // 2. (Responsive) image is optional for Video or image as CSS background.
      if ($blazies->get('resimage.id')) {
        self::buildResponsiveImage($variables);
      }
      else {
        self::buildImage($variables);
      }

      // 3. The bgs is output specific for CSS background purposes with BC.
      // This is applied to both Responsive and plain old images.
      if ($bgs = $blazies->get('bgs')) {
        self::background($attributes, $blazies, $bgs);
      }

      // 4. Prepare iframe, and allow a tiny video preview without iframe.
      if ($blazies->use('iframe') && !$blazies->is('noiframe')) {
        self::buildIframe($variables);
      }
    }

    // 5. (Responsive) image is optional for Video, or image as CSS background.
    if ($variables['image'] || $bgs) {
      if ($variables['image']) {
        self::image($variables);
      }

      // 6. Only blur if it has an image, or BG, including the media player.
      if ($blazies->use('blur')) {
        Placeholder::blur($variables, $settings);
      }
    }

    // 7. Multi-breakpoint aspect ratio only applies if lazyloaded.
    // These may be set once at formatter level, or per breakpoint above.
    // Only relevant if Fluid is selected for Aspect ratio, else a leak.
    if ($blazies->is('fluid') && !$blazies->is('undata')) {
      if ($ratios = $blazies->get('ratios', [])) {
        $provider = $blazies->get('media.provider');
        if (!Internals::irrational($provider)) {
          $attributes[self::data($blazies, 'ratios')] = Json::encode($ratios);
        }
      }
    }
  }

  /**
   * Returns the expected/ corrected attribute to avoid potential conflicts.
   *
   * @param object $blazies
   *   The given blazies object.
   * @param string $attr
   *   The given attribute.
   *
   * @return string
   *   The updated attr.
   */
  public static function data($blazies, $attr): string {
    // @todo use data-b- at/by 3.x to avoid potential conflicts.
    $prefix = $blazies->use('data_b') ? 'data-b-' : 'data-';
    return $prefix . $attr;
  }

  /**
   * Returns common iframe attributes, including those not handled by blazy.
   *
   * @param array $settings
   *   The given settings.
   *
   * @return array
   *   The iframe attributes.
   */
  public static function iframe(array &$settings): array {
    $blazies = Internals::getBlazies($settings);
    $attributes = ['allowfullscreen' => TRUE];

    // Already escaped upstream for core, except for contribs.
    $embed_url = $blazies->get('media.embed_url');
    if (!$blazies->get('media.escaped')) {
      $embed_url = UrlHelper::stripDangerousProtocols($embed_url);
    }

    // Listens to hook_alters.
    if ($attrs = $blazies->get('iframe.attributes', [])) {
      unset($attrs['src']);
      $attributes = Arrays::merge($attributes, $attrs);
    }

    // Native lazyload just loads the URL directly.
    // With many videos like carousels on the page may chaos, but we provide a
    // solution: use `Image to iframe` for GDPR, swipe and best performance.
    if (Internals::isUndata($blazies)) {
      $attributes['src'] = $embed_url;

      // Inside CKEditor must disable interactive elements.
      if ($blazies->is('sandboxed')) {
        $attributes['sandbox'] = TRUE;
      }
    }
    // Non-native lazyload for oldies to avoid loading src, the most efficient.
    // No cookies are loaded from external sites till the play button clicked,
    // only if choosing `Image to iframe` Media switch. This used to be printed
    // at early 1.x, but no longer since we have JS media player.
    else {
      $attributes['class'][] = 'b-lazy';
      $attributes['data-src'] = $embed_url;
      $attributes['src'] = 'about:blank';
    }

    // Makes query selector easier for filter.
    if ($blazies->get('filter')) {
      $attributes['class'][] = 'b-filter';
    }

    // Just in case merges cause similar discreet issues to images.
    // This is the root cause for the failing lazy load: data-entity-type!
    // This attribute reset lazy data:image SRC attribute after Blazy causing
    // failing lazy-load discreet behaviors, relevant for BlazyFilter:
    // See https://www.drupal.org/project/blazy/issues/3374519
    unset($attributes['data-entity-type']);

    self::common($attributes, $blazies);
    return $attributes;
  }

  /**
   * Modifies inline style to not nullify others.
   *
   * @param array $attributes
   *   The attributes being modified.
   * @param string $css
   *   The css value.
   */
  public static function inlineStyle(array &$attributes, string $css): void {
    $attributes['style'] = ($attributes['style'] ?? '') . $css;
  }

  /**
   * Defines attributes, builtin, or supported lazyload such as Slick/ Splide.
   *
   * These attributes can be applied to either IMG or DIV as CSS background.
   * The [data-(src|srcset)] attributes are applicable for (Responsive) image.
   * While [data-src] is reserved by Blazy.
   * The data-[SRC|SCRSET] is if `nojs` disabled, background, or video.
   *
   * @param array $attributes
   *   The attributes being modified.
   * @param \Drupal\blazy\BlazySettings $blazies
   *   The given $blazies.
   * @param bool $bg
   *   If a background image.
   */
  public static function lazy(array &$attributes, $blazies, bool $bg = FALSE): void {
    if ($url = $blazies->get('image.url')) {
      $trusted = $blazies->get('image.trusted');
      $url = $trusted ? $url : UrlHelper::stripDangerousProtocols($url);
      $unlazy = Internals::isUnlazy($blazies);
      $unlazy_bg = Internals::isUnlazyBg($blazies);

      // Makes query selector easier for filter.
      if ($blazies->get('filter')) {
        $attributes['class'][] = 'b-filter';
      }

      // Required by ratio, or to fix broken 404 data URI Views rewrite.
      // Can be refined for BG, Audio, Video, HTML beyond IMG/IFRAME, but not
      // worth the effort, and inevitable complexity: AMP, sandboxed, lazy, etc.
      // If No Javascript enabled, heavy lifting is taken care of by Native lazy
      // except for things it doesn't solve: BG, Audio, Video, HTML.
      $attributes['class'][] = $blazies->get('lazy.class', 'b-lazy');

      // Native, or unlazy, has .blazy--nojs at container to fix issues, if any.
      // BG is not supported by Native lazyload, enforce lazy.
      if (!$unlazy || $bg) {
        $attribute = $blazies->get('lazy.attribute', 'src');
        $attributes['data-' . $attribute] = $url;
      }

      // If BG in static AMP, sandboxed, or hero.
      if ($bg && $unlazy_bg) {
        self::inlineStyle($attributes, 'background-image: url(' . $url . ');');
      }
    }
  }

  /**
   * Return the image alt and title, also accounts for multimedia and UGC.
   *
   * @param \Drupal\blazy\BlazySettings $blazies
   *   The given $blazies.
   * @param object|null $item
   *   If a background image.
   *
   * @return array
   *   The al and title array.
   */
  public static function altTitle($blazies, $item = NULL): array {
    [
      'alt' => $alt,
      'title' => $title,
    ] = self::altTitleRaw($blazies, $item);

    // Ensures no double escapes since it might called anywhere.
    if ($blazies->get('image.escaped')) {
      return ['alt' => $alt ?: '', 'title' => $title];
    }

    // Updates $title whether for audio/ video, or just image.
    $title = self::escape($title);
    $alt   = self::escape($alt);

    // Overrides title if to be used as a placeholder for multimedia.
    if ($blazies->is('multimedia') && $title) {
      $_title = $title;
      $bundle = $blazies->get('media.bundle', 'remote_video');
      $bundle = str_replace('remote_', '', $bundle);
      $bundle = str_replace('_', ' ', $bundle);

      // Prioritize editable user inputs rather than external sites'.
      $blazies->set('media.label', $title);

      $translation = ['@bundle' => $bundle, '@label' => $title];
      $title = self::mediaTitle($translation);

      if ($alt) {
        if ($alt == $_title) {
          $alt = $title;
        }
        else {
          $translation['@alt'] = $alt;
          $alt = new TranslatableMarkup('Preview image for the @bundle "@label" - @alt.', $translation);
        }
      }
      else {
        $alt = $title;
      }
    }

    // Redefine for good reasons.
    $blazies->set('image.alt', $alt)
      ->set('image.title', $title)
      ->set('image.escaped', TRUE);

    return ['alt' => $alt ?: '', 'title' => $title];
  }

  /**
   * Return the escaped string.
   *
   * @param string|null $text
   *   The text to escape.
   * @param bool $strip
   *   Whether stripped.
   *
   * @return string|null
   *   The escaped text or empty.
   */
  public static function escape($text, bool $strip = FALSE): ?string {
    if ($text) {
      if ($strip) {
        $text = strip_tags($text);
      }

      $text = Html::escape($text);
      // Twig will escape Can't to Can&#039;t, else doubles: Can&amp;#039;t.
      // @todo recheck if the world is ended with this, and so remove this.
      $text = str_replace('&#039;', "'", $text);
    }
    return $text;
  }

  /**
   * Return the raw image alt and title, normally for captions, not attributes.
   *
   * @param \Drupal\blazy\BlazySettings $blazies
   *   The given $blazies.
   * @param object|null $item
   *   If a background image.
   */
  private static function altTitleRaw($blazies, $item = NULL): array {
    // Ensures no double processes.
    if ($blazies->get('image.raw.processed')) {
      return $blazies->get('image.raw');
    }

    // Plain hard-coded filter/ external image might not have ImageItem object.
    // Soundcloud/remote videos (Vimeo, Youtube, etc) have meaningful titles.
    $title = $blazies->is('image')
      ? $blazies->get('image.title')
      : $blazies->get('media.label');
    $alt = $blazies->get('image.alt');

    // @todo deprecate and remove this item check at 3.x, once they are all in blazies.image.
    if ($item) {
      // Title from fake item might be just file name, except from BlazyFilter.
      // Needed by thumbnails if any image item, fake or real, no biggies.
      $alt = empty($item->alt) ? $alt : trim($item->alt);
      $desc = $item->description ?? NULL;

      // File SVG with description_field enabled.
      if (!$alt && $desc = $blazies->get('image.description', $desc)) {
        $alt = $desc;
      }

      // Do not output an empty 'title' attribute.
      if (isset($item->title)) {
        $title = mb_strlen($item->title) != 0 ? trim($item->title) : '';
      }
    }

    // Might be abused to use HTML, fine for captions, but not attributes.
    // This should make both parties happier ever after, sort of.
    // strip_tags always sounds harsh, but not when done for a noble purpose.
    $ext = $blazies->get('image.extension', 'x');

    // Alt from fake image factory might be just file name.
    if ($alt) {
      $alt = strpos($alt, '.' . $ext) !== FALSE ? '' : strip_tags($alt);
    }

    // Prevents default ugly media.label filename as popup image title.
    if ($title) {
      $title = strpos($title, '.' . $ext) !== FALSE ? '' : strip_tags($title);
    }

    // Ensures called once, else filled up even when it should be empty.
    $blazies->set('image.raw.alt', $alt)
      ->set('image.raw.title', $title)
      ->set('image.raw.processed', TRUE);

    return ['alt' => $alt, 'title' => $title];
  }

  /**
   * Provide common attributes for IMG and IFRAME/VIDEO elements.
   *
   * @param array $attributes
   *   The attributes being modified.
   * @param \Drupal\blazy\BlazySettings $blazies
   *   The given $blazies.
   *
   * @todo at 2022/2 core has no loading Responsive.
   */
  private static function common(array &$attributes, $blazies): void {
    $attributes['class'][] = 'media__element';
    $loading = $blazies->get('image.loading', 'lazy');
    $heroes = in_array($loading, ['slider', 'unlazy']);

    // The fetchpriority is mostly relevant with slider architecture, and
    // applicable to limited media: IMG and IFRAME. Just a hint, not mandatory.
    // This option is limited to CWV:LCP hero image, and can be applied to
    // non-slider hero image with `unlazy` option. Major browser supports are
    // massive as per 2026/1/3.
    // Slick/Splide can also display a single hero image.
    // Only one image can have fetchpriority=high on a page. That is why it is
    // limited only to the designated LCP as a hero image.
    // See https://developer.mozilla.org/en-US/docs/Web/HTML/Reference/Attributes/fetchpriority
    if ($heroes) {
      // A hero image needs a high priority. Hidden images should be deferred.
      // It is the modern "turbo" button that signals to the browser to
      // prioritize this asset over non-critical CSS or JavaScript.
      $attributes['fetchpriority'] = $blazies->is('lcp') ? 'high' : 'low';

      // Only if Heroes, prevents from aggressive hijacks by global "Auto-lazy"
      // scripts or browser data-saver heuristics which might ruin LCP scores
      // using explicit `eager`, safer than negligible byte shaver.
      $loading = $blazies->is('lcp') ? 'eager' : 'lazy';
    }

    // Sets the loading attributes; dimensions should be no longer a concern
    // with most use cases, even with careless text editors, already taken care
    // of by Core for text editors, or Blazy for the fields. Except negligible
    // external URLs.
    $attributes['loading'] = $loading;
  }

  /**
   * Modifies $variables to provide background (Responsive) image attributes.
   *
   * @param array $attributes
   *   The attributes being modified.
   * @param \Drupal\blazy\BlazySettings $blazies
   *   The given $blazies.
   * @param \stdClass $bgs
   *   The background images.
   */
  private static function background(array &$attributes, $blazies, $bgs): void {
    $str = Json::encode($bgs);
    $attributes['class'][] = 'b-bg';

    if ($blazies->use('encodedbox')) {
      $str = base64_encode($str);
      $attributes['class'][] = 'is-b-encoded';
    }

    $attributes['data-b-bg'] = $str;

    // If using BG, store title in the permanent container.
    if ($blazies->is('multimedia') && $title = self::altTitle($blazies)['title']) {
      $attributes['title'] = $title;
    }
  }

  /**
   * Modifies $variables to provide optional (Responsive) image attributes.
   *
   * @param array $variables
   *   The variables being modified.
   */
  private static function image(array &$variables): void {
    /** @var array $settings */
    $settings   = &$variables['settings'];
    $image      = &$variables['image'];
    $attributes = &$variables['item_attributes'];
    $blazies    = Internals::getBlazies($settings);

    // Sticks to blazy.api.php design to avoid issues with image styles, etc.
    if ($attrs = $blazies->get('image.attributes', [])) {
      unset($attrs['src']);
      $attributes = Arrays::merge($attributes, $attrs);
    }

    // Provides image alt and title, and also accounts for multimedia.
    $attributes['alt'] = $blazies->get('image.alt', '');

    if ($title = $blazies->get('image.title')) {
      $attributes['title'] = $title;
    }

    // LCP images should be sync or without decoding.
    // The Danger of async for LCP: decoding="async" tells the browser it can
    // delay the painting of the image to keep the main thread free for other
    // tasks (like JS). For an LCP image, this is the opposite. We want the
    // pixels on the screen as fast as possible to comply with Core Web Vitals.
    // The Problem with sync for LC: While decoding="sync" forces the browser
    // to paint the image immediately, it can technically block the main thread.
    // However, the most important point is that browsers already default to the
    // most efficient decoding path for high-priority images.
    // By omitting the attribute for LCP:
    // * Save the bytes, even if negligible.
    // * Allow the browser's engine to make the optimal choice based on current
    // CPU/GPU load.
    // * Avoid the risk of async accidentally pushing the LCP paint to a later
    // frame.
    // Never use decoding="async" on a Hero image, as it gives the browser
    // permission to delay the very pixels our LCP score depends on.
    // https://developer.mozilla.org/en-US/docs/Web/API/HTMLImageElement/decode.
    if (!$blazies->is('lcp')) {
      $attributes['decoding'] = 'async';
    }

    // Preserves UUID for sub-module lookups, relevant for BlazyFilter.
    if ($uuid = $blazies->get('entity.uuid')) {
      $attributes['data-entity-uuid'] = $uuid;
    }

    // Only output dimensions for non-svg. Respects hand-coded image attributes.
    // Do not pass it to $attributes to also respect both (Responsive) image.
    // Also supports svg dimensions, if any.
    if (!isset($attributes['width']) && $width = $blazies->get('image.width')) {
      $image['#height'] = $blazies->get('image.height');
      $image['#width']  = $width;
    }

    // This is the root cause for the failing lazy load: data-entity-type!
    // This attribute reset lazy data:image SRC attribute after Blazy causing
    // failing lazy-load discreet behaviors, relevant for BlazyFilter:
    // See https://www.drupal.org/project/blazy/issues/3374519
    unset($attributes['data-entity-type']);

    // Apply common shared attributes.
    self::common($attributes, $blazies);
    $image['#attributes'] = Arrays::merge($attributes, $image, '#attributes');

    // Provides a noscript if so configured, before any lazy defined.
    // Not needed at preview mode, or when native lazyload takes over.
    if ($blazies->ui('noscript') && !$blazies->is('unlazy')) {
      self::buildNoscriptImage($variables);
    }

    // Provides [data-(src|lazy)] for (Responsive) image, after noscript.
    self::lazy($image['#attributes'], $blazies);
  }

  /**
   * Modifies variables for blazy (non-)lazyloaded image.
   *
   * @param array $variables
   *   The variables being modified.
   */
  private static function buildImage(array &$variables): void {
    /** @var array $settings */
    $settings    = &$variables['settings'];
    $attributes  = &$variables['attributes'];
    $blazies     = Internals::getBlazies($settings);
    $url         = $blazies->get('image.url');
    $placeholder = $blazies->get('placeholder.url') ?: Placeholder::generate();

    // Supports either lazy loaded image, or not.
    if ($blazies->use('bg')) {
      // Attach BG data attributes to a DIV container.
      // Background is not supported by Native, cannot use unlazy, use undata:
      // - undata: no use of dataset (data-b-bg) like at AMP, or preview pages.
      // - unlazy: `No JavaScript: lazy` aka decoupled lazy loader + undata.
      $style = $blazies->get('image.style');
      $width = $blazies->get('image.width') ?: 101;
      // @fixme background is screwed up somehow, only when using core image as
      // source image upstream, fine when given blazy image formatter.
      // $unlazy = $blazies->is('undata');
      // $url = $unlazy ? $url : $placeholder;
      // $blazies->set('image.url', $url);
      // ->set('is.unlazy', $unlazy);
      $data = $settings;
      $data['width'] = $width;
      $data['height'] = $blazies->get('image.height');
      $blazies->set('bgs.' . $width, Image::background($data, $style));
      self::lazy($attributes, $blazies, TRUE);
    }
    else {
      // Do not use theme_image_style(), else more complication with SVG, etc.
      $image = &$variables['image'];
      $image['#theme'] = 'image';
      $image['#uri'] = Internals::isUnlazy($blazies) ? $url : $placeholder;
    }
  }

  /**
   * Provides (Responsive) image noscript if so configured.
   *
   * @param array $variables
   *   The variables being modified.
   */
  private static function buildNoscriptImage(array &$variables): void {
    /** @var array $settings */
    $settings = $variables['settings'];
    $blazies  = Internals::getBlazies($settings);
    $noscript = $variables['image'];

    $noscript['#uri'] = $blazies->get('resimage.id')
      ? $blazies->get('image.uri')
      : $blazies->get('image.url');

    $noscript['#attributes']['data-b-noscript'] = TRUE;

    $variables['noscript'] = [
      '#type' => 'inline_template',
      '#template' => '{{ prefix | raw }}{{ noscript }}{{ suffix | raw }}',
      '#context' => [
        'noscript' => $noscript,
        'prefix' => '<noscript>',
        'suffix' => '</noscript>',
      ],
    ];
  }

  /**
   * Modifies variables for responsive image.
   *
   * Responsive images with height and width save a lot of calls to
   * image.factory service for every image and breakpoint in
   * _responsive_image_build_source_attributes(). Very necessary for
   * external file system like Amazon S3.
   *
   * @param array $variables
   *   The variables being modified.
   */
  private static function buildResponsiveImage(array &$variables): void {
    /** @var array $settings */
    $settings = &$variables['settings'];
    $blazies  = Internals::getBlazies($settings);

    if ($blazies->use('bg')) {
      // Attach BG data attributes to a DIV container.
      $attributes = &$variables['attributes'];
      ResponsiveImage::background($attributes, $settings);
    }
    else {
      $image = &$variables['image'];
      $image['#theme'] = 'responsive_image';
      $image['#responsive_image_style_id'] = $blazies->get('resimage.id');
      $image['#uri'] = $blazies->get('image.uri');

      if (!$blazies->is('unlazy')) {
        $image['#attributes'] = [
          'data-b-lazy' => $blazies->ui('one_pixel'),
          'data-b-ui' => $blazies->ui('placeholder'),
          'data-b-placeholder' => $blazies->get('placeholder.url'),
        ];
      }
    }
  }

  /**
   * Returns the classes applicable only to the first, not nested containers.
   *
   * @param array $attributes
   *   The attributes being modified.
   * @param \Drupal\blazy\BlazySettings $blazies
   *   The given $blazies.
   * @param array $options
   *   The options.
   */
  private static function firstClasses(array &$attributes, $blazies, array $options): array {
    [
      'namespace' => $namespace,
      'lightbox'  => $lightbox,
      'switcher'  => $switcher,
    ] = $options;

    $classes   = [];
    $add_class = !$blazies->ui('wrapper_class');

    // For CSS fixes.
    if ($blazies->is('unlazy')) {
      $classes[] = 'blazy--nojs';
    }

    if ($blazies->use('bg')) {
      $classes[] = 'is-b-bg';
    }

    // Specific for media switcher, lightbox or not.
    if ($switcher) {
      $switch = str_replace('_', '-', $switcher);
      $attributes['data-' . $switch . '-gallery'] = TRUE;

      $classes[] = 'blazy--' . $switch;

      if ($blazies->is('lightbox')) {
        $classes[] = 'blazy--lightbox';
        $classes[] = 'blazy--' . $switch . '-gallery';

        // Allows lightboxes to inject their optionset, if any.
        // More accessible and contextual than in the <HEAD> or <SCRIPT> tags.
        if ($extras = $blazies->get('data.' . $lightbox)) {
          $attributes['data-' . $switch] = is_string($extras) ? $extras : Json::encode($extras);
        }
      }
    }

    foreach (['field', 'view'] as $key) {
      if ($blazies->get($key . '.name')) {
        $classes[] = $namespace . '--' . $key;
      }
    }

    // @todo deprecate and remove the last -- for - at 3.x.
    if ($add_class) {
      foreach (['field', 'view'] as $key) {
        if ($name = $blazies->get($key . '.name')) {
          $name = str_replace('_', '-', $name);
          $name = $key == 'view' ? 'view--' . $name : $name;
          $classes[] = $namespace . '--' . $name;

          if ($view_mode = $blazies->get($key . '.view_mode')) {
            $view_mode = str_replace('_', '-', $view_mode);
            $classes[] = $namespace . '--' . $name . '--' . $view_mode;
          }

          // See BlazyAlter::blazySettingsAlter().
          if ($id = $blazies->get('view.instance_id')) {
            $id = str_replace('_', '-', $id);
            $classes[] = $namespace . '--view--' . $id;
          }
        }
      }
    }

    return $classes;
  }

  /**
   * Return the image title.
   *
   * @param array $translation
   *   The translation array.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The translatable markup.
   */
  private static function mediaTitle(array $translation): TranslatableMarkup {
    return new TranslatableMarkup('Preview image for the @bundle "@label".', $translation);
  }

}
