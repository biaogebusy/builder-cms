<?php

namespace Drupal\blazy\Media;

use Drupal\Component\Utility\UrlHelper;
use Drupal\blazy\BlazySettings;
use Drupal\blazy\Internals\CheckItem;
use Drupal\blazy\Internals\Internals;

/**
 * Provides preload utility.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module.
 *
 * @todo recap similiraties and make them plugins.
 */
class Preloader {

  /**
   * Preload late-discovered resources for better performance.
   *
   * @see https://web.dev/preload-critical-assets/
   * @see https://caniuse.com/?search=preload
   * @see https://developer.mozilla.org/en-US/docs/Web/HTML/Link_types/preload
   * @see https://developer.chrome.com/blog/new-in-chrome-73/#more
   * @nottodo support multiple hero images like carousels.
   */
  public static function preload(array &$load, array $settings): void {
    $blazies = Internals::getBlazies($settings);
    $images  = $blazies->get('images', []);
    $check   = array_filter($images);
    $sources = $blazies->get('resimage.sources', []);
    $initial = $blazies->get('initial', -1);
    $inits   = $check[$initial] ?? [];

    // A hero is not always 0 for sliders basing on `start` or `InitialSlide`
    // However, 0 is always there since the logic is JS, not PHP; except for the
    // 3.0.18 Blazy Layout Hero which may not always have media on first region
    // given the potential of Native Grid complex design.
    if (empty($check) || empty($inits['uri'])) {
      return;
    }

    $links = self::generate($images, $sources, $blazies);
    foreach ($links as $key => $value) {
      if ($value) {
        $load['html_head'][$key] = $value;
      }
    }
  }

  /**
   * Extracts uris from file/ media entity, relevant for the new option Preload.
   *
   * @requires image styles defined via Image::styles().
   *
   * Also extract the found image for gallery/ zoom like, ElevateZoomPlus, etc.
   *
   * @todo merge urls here as well once puzzles are solved: URI may be fed by
   * field formatters like this one, blazy_filter, views field, or manual call.
   */
  public static function prepare(array &$settings, $items, array $entities = []): void {
    $blazies = Internals::getBlazies($settings);
    if (array_filter($blazies->get('images', []))) {
      return;
    }

    $style = $blazies->get('image.style');

    $func = function ($item, $entity, $delta = 0) use (&$settings, $blazies, $style) {
      $options  = ['entity' => $entity, 'settings' => $settings];
      $image    = Image::item($item, $options);
      $uri      = Uri::fromImage($image);
      $valid    = Uri::isValid($uri);
      $unstyled = $uri ? CheckItem::unstyled($settings, $uri) : FALSE;
      $url      = Url::fromAny($settings, $style, $uri);

      // Only needed the first found image, no problem which with mixed media.
      if ($uri && !$blazies->get('first.uri')) {
        $blazies->set('first.url', $url)
          ->set('first.item', $image)
          ->set('first.unstyled', $unstyled)
          ->set('first.uri', $uri);

        // The first image dimensions to differ from individual item dimensions.
        Image::dimensions($settings, $image, $uri, TRUE);
      }

      // Ensures the Hero is the image being displayed, not original URI.
      $style_uri = NULL;
      if ($style && $url) {
        $style_uri = Uri::build($url);
      }

      // @todo also pass $style + $image when all sources covered.
      return $uri ? [
        'delta'     => $delta,
        'unstyled'  => $unstyled,
        'uri'       => $uri,
        'url'       => $url,
        'valid'     => $valid,
        'uri_style' => $style_uri,
      ] : [];
    };

    $empties = $images = [];
    foreach ($items as $key => $item) {
      $image = [];

      // Priotize image file, then Media, etc.
      $entity = is_object($item) && isset($item->entity) ? $item->entity : NULL;
      if (!$entity) {
        $entity = $entities[$key] ?? NULL;
      }

      // Respects empty URI to keep indices intact for correct mixed media.
      $image = $func($item, $entity, $key);

      $images[$key] = $image;

      if (empty($image['uri'])) {
        $empties[$key] = TRUE;
      }
    }

    // $empty = count($empties) == count($images);
    // @todo recheck and remove if this causes broken indices.
    // @todo renable $images = $empty ? array_filter($images) : $images;
    // This is also required by ResponsiveImage::sources().
    $blazies->set('images', $images, TRUE);

    // Checks for [Responsive] image dimensions and sources for formatters
    // and filters. Sets dimensions once, if cropped, to reduce costs with ton
    // of images. This is less expensive than re-defining dimensions per image.
    // These also provide data for the Preload option.
    if (!$blazies->was('resimage_dimensions')) {
      $unstyled = $blazies->get('first.unstyled', FALSE);
      $resimage = $blazies->get('resimage.style');

      // @todo recheck $blazies->get('first.uri').
      if (!$unstyled) {
        if ($heroes = $blazies->get('heroes')) {
          if ($manager = Internals::blazy()) {
            if ($hero_style = $heroes['responsive_image_style'] ?? NULL) {
              if (!$blazies->get('heroes.responsive_image.id')) {
                $resimage = $manager->load($hero_style, 'responsive_image_style') ?: $resimage;
              }
            }
          }
        }
        else {
          $resimage = ResponsiveImage::toStyle($settings, $unstyled);
        }

        if ($resimage) {
          ResponsiveImage::dimensions($settings, $resimage, TRUE);

          $blazies->set('heroes.reponsive_image.style', $resimage)
            ->set('heroes.reponsive_image.id', $resimage->id());
        }
        elseif ($style) {
          Image::cropDimensions($settings, $style);
        }
      }
      $blazies->set('was.resimage_dimensions', TRUE);
    }
  }

  /**
   * Generates preload urls.
   */
  private static function generate(
    array $images,
    array $sources,
    BlazySettings $blazies,
  ): \Generator {
    $loading = $blazies->get('image.loading', 'lazy');
    $heroes = in_array($loading, ['slider', 'unlazy']);
    $priority = $blazies->use('bg', FALSE) && $heroes;

    $link = function (array $image, $item = NULL): array {
      $uri = $image['uri'] ?? NULL;
      $url = $image['url'] ?? NULL;
      $valid = $image['valid'] ?? FALSE;
      $hero = $image['hero'] ?? FALSE;
      $uri_style = $image['uri_style'] ?? $uri;

      // Suppress useless warning of likely failing initial image generation.
      // Better than checking file exists.
      // Each field may have different mime types for each image just like URIs.
      // Non-transliterated URL with weird characters may fail, add fallback.
      $mime = @mime_content_type($uri_style) ?: 'image/jpeg';

      // Responsive image.
      if ($item && $item_type = $item['type'] ?? NULL) {
        $mime = $item_type->value() ?: $mime;
      }

      [$type] = array_map('trim', explode('/', $mime, 2));
      $key = hash('md2', $url);

      $attrs = [
        'rel'  => 'preload',
        'as'   => $type,
        'href' => $valid ? $url : UrlHelper::stripDangerousProtocols($url),
        'type' => $mime,
      ];

      // Responsive image.
      $suffix = '';
      if ($item) {
        if ($srcset = $item['srcset'] ?? NULL) {
          $suffix = '_responsive';
          $attrs['imagesrcset'] = $srcset->value();

          if ($sizes = $item['sizes'] ?? NULL) {
            $attrs['imagesizes'] = $sizes->value();
          }
        }
      }

      // Only if BG and a hero image, set the fetchpriority. For non-BG, an
      // inline fetchpriority in IMG/IFRAME is provided instead.
      // It is the modern "turbo" button that signals to the browser to
      // prioritize this asset over non-critical CSS or JavaScript.
      // It ensures the preload itself is treated as the highest priority
      // request, even before the browser has finished parsing the rest of the
      // head.
      if ($hero) {
        $attrs['fetchpriority'] = 'high';
      }

      // Checks for external URI.
      if (UrlHelper::isExternal($url)) {
        $attrs['crossorigin'] = TRUE;
      }

      return [
        [
          '#tag' => 'link',
          '#attributes' => $attrs,
        ],
        'blazy' . $suffix . '_' . $type . $key,
      ];
    };

    // Responsive image with multiple sources.
    if ($sources) {
      foreach ($sources as $delta => $source) {
        $uri   = $source['uri'] ?? NULL;
        $url   = $source['fallback'] ?? NULL;
        $valid = $source['valid'] ?? TRUE;
        $start = $delta == $blazies->get('initial', -1);

        // Preloading 1px data URI makes no sense, see if image_url exists.
        $data_uri = Uri::isDataUri($url);
        if ($data_uri && $url2 = $source['url'] ?? NULL) {
          $url = $url2;
        }

        $image = [
          'uri' => $uri,
          'url' => $url,
          'valid' => $valid,
          'hero' => $priority && $start,
        ];

        // @todo recheck items is provided somewhere.
        $items = $source['items'] ?? [];
        foreach ($items as $source_item) {
          yield empty($source_item['srcset']) || !$start ? NULL : $link($image, $source_item);
        }
      }
    }
    else {
      // Regular plain old images.
      foreach ($images as $delta => $image) {
        // Indices might be preserved even empty/ failing URI, etc.
        $uri   = $image['uri'] ?? NULL;
        $url   = $image['url'] ?? NULL;
        $start = $delta == $blazies->get('initial', -1);

        $image['hero'] = $priority && $start;

        // URI might be empty with mixed media, but indices are preserved.
        yield $uri && $url && $start ? $link($image) : NULL;
      }
    }
  }

}
