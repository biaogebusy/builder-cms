<?php

namespace Drupal\blazy\Media;

use Drupal\Core\Entity\ContentEntityInterface;
// @todo enable at D11 use Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItemInterface;
// @todo enable at D11 use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\blazy\BlazyDefault;
use Drupal\blazy\BlazySettings;
use Drupal\blazy\Internals\Internals;
use Drupal\file\FileInterface;
use Drupal\image\ImageStyleInterface;
use Drupal\image\Plugin\Field\FieldType\ImageItem;
use Drupal\media\MediaInterface;

/**
 * A common image utility helper.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module.
 *
 * @todo tighten the callers, or leave it for the lazy.
 */
class Image {

  /**
   * Checks if the image style contains crop in the effect name.
   *
   * @var array
   */
  protected static $crop;

  /**
   * Checks if image dimensions are set.
   *
   * @var array
   */
  protected static $isCropSet;

  /**
   * Sets dimensions once to reduce method calls, if image style contains crop.
   *
   * @param array $settings
   *   The settings being modified.
   * @param \Drupal\image\ImageStyleInterface|null $style
   *   The image style to check for corp effect.
   *
   * @todo add union types at 4.x: ImageStyleInterface|null $style.
   */
  public static function cropDimensions(
    array &$settings,
    $style,
  ): void {
    if (!$style instanceof ImageStyleInterface) {
      return;
    }

    $id = $style->id();

    if (!isset(static::$isCropSet[$id])) {
      // If image style contains crop, sets dimension once, and let all inherit.
      if ($crop = self::getCrop($style)) {
        $blazies = Internals::getBlazies($settings);
        $data = self::transformDimensions($crop, $blazies);

        // Informs individual images that dimensions are already set once.
        // Do not let the first broken image screw up the rest, likely
        // non-transliterated file names, SVG, missing ones, etc.
        if ($data['width']) {
          $blazies->set('image', $data, TRUE)
            ->set('is.dimensions', TRUE);
        }
      }

      static::$isCropSet[$id] = TRUE;
    }
  }

  /**
   * Returns CSS background image array.
   *
   * @todo refactor this, to get rid of settings for blazies object at/ by 3.x.
   *
   * @param array $settings
   *   The given settings.
   * @param \Drupal\image\ImageStyleInterface|null $style
   *   The given name.
   *
   * @return array
   *   The image src and aspect ratio array.
   *
   * @todo add union types at 4.x:
   * ImageStyleInterface|null $style.
   */
  public static function background(
    array $settings,
    $style = NULL,
  ): array {
    // @tbd replace src with URL before 3.x, or keep it.
    return [
      'src' => Url::fromAny($settings, $style),
      'ratio' => Ratio::compute($settings),
    ];
  }

  /**
   * Provides original unstyled image dimensions based on the given image item.
   *
   * This one is original image, not styled like self:transformDimensions().
   * Sources: formatters, filters or any hard-coded unmanaged files like VEF.
   *
   * @param array $settings
   *   The modified settings.
   * @param \stdClass|\Drupal\image\Plugin\Field\FieldType\ImageItem|null $item
   *   The image item or null.
   * @param string|null $uri
   *   The modified settings.
   * @param bool $initial
   *   Whether an initial image.
   *
   * @return array
   *   The image src and aspect ratio array.
   *
   * @todo add union types at 4.x:
   * stdClass|ImageItem|null $item, string|null $uri.
   */
  public static function dimensions(
    array &$settings,
    $item,
    $uri,
    bool $initial = FALSE,
  ): array {
    $blazies = Internals::getBlazies($settings);
    $_width  = 'width';
    $_height = 'height';
    $fluid   = $blazies->is('fluid');
    $which   = $initial ? 'first' : 'image';
    $height  = $blazies->get($which . '.height');
    $width   = $blazies->get($which . '.width');
    $uri     = $uri ?: $blazies->get($which . '.uri');

    // Original image sizes are stored within ImageItem, or fake one.
    // @todo deprecate and remove ImageItem checks at 3.x. when all moved into
    // blazies.image.
    if ($item) {
      // The given item might also be VideoEmbedField, unless converted using
      // BlazyOEmbed::getThumbnail(). Ensures it is not screwing up.
      if (!isset($item->width)) {
        $item = $blazies->get('image.item');
      }

      $width = $item->width ?? $width;
      $height = $item->height ?? $height;

      // Ensures the correct image item is set here on.
      $blazies->set('image.item', $item);
    }

    // Only applies when no file API, no $item, with unmanaged VEF/ WYSIWG/
    // filter image, and when image_style even failed.
    if ($uri && (!$height || !$width)) {
      $abs = $blazies->get('image.uri_root', $uri);
      $abs = Uri::toAccessibleUri($abs);

      if (Uri::isValid($abs) && !$blazies->get('image.valid')) {
        $blazies->set('image.uri', $abs);
      }

      // Prevents 404 warning when video thumbnail missing for a reason.
      if (!Url::isExternal($uri)) {
        if ($dimensions = @getimagesize($abs)) {
          [$width, $height] = $dimensions;
        }
      }
    }

    // Since 2.17, the last two standing settings along with URI, now gone for
    // good into blazies object.
    $check[$_width] = $width;
    $check[$_height] = $height;

    // Sometimes they are string, cast them integer to reduce JS logic.
    self::toInt($check, $_width, $_height);

    // Defines original dimensions.
    $data = ['width' => $check[$_width], 'height' => $check[$_height]];

    // Image styles might be left empty, and aspect ratio is used.
    if ($fluid && !$blazies->is('unstyled')) {
      $dims = $data;
      $dims['ratios'] = $blazies->get('css.ratio');

      // The result is normally used for non-inline style, via CSS rules.
      $data['fluid'] = Ratio::fluid($dims);
    }

    // The result is normally used for inline style via padding hacks.
    $data['ratio'] = Ratio::compute($data);

    // If initial call, used by EZ, etc.
    if ($initial || !$blazies->get('first.width')) {
      $blazies->set('first', $data, TRUE);
    }

    // Only if not cropped uniformly.
    if (!$blazies->is('dimensions')) {
      $blazies->set('image', $data, TRUE);
    }

    // In case `image_style` is not provided.
    $blazies->set('image.original', $data, TRUE);
    return $data;
  }

  /**
   * Returns the image item out of File entity, ER, etc., or just $settings.
   *
   * @param object $object
   *   The optional Media, File entity, or ER, etc. to get image item from.
   * @param array $settings
   *   The optional settings.
   *
   * @return object|null
   *   The object of image item, or NULL.
   *
   * @todo simplify this, like everything else. An obvious confusion here.
   * @todo return image item directly without settings.
   */
  public static function fromAny($object, array &$settings): ?object {
    $blazies = Internals::verify($settings);
    $output  = $uri = NULL;

    // If Media entity, we must have a File entity, and likely ImageItem.
    if ($object instanceof MediaInterface) {
      $entity = $object;
    }
    else {
      // Extracts File entity from any object or settings, if applicable.
      // Node, EntityReferenceRevisionsItem, etc.
      // We do not come from BlazyFileFormatter, and co, here on. Instead
      // called by BlazyFilter file upload and legacy BlazyViewsFieldFile.
      $entity = File::item($object, $settings);

      if (File::isValid($entity)) {
        if ($output = self::fakeFromFactory($blazies, $entity)) {
          $uri = $output->uri;
        }
      }
    }

    // Called by entity formatters, excluding file.
    if (empty($output)) {
      $options = [
        'entity'   => $entity,
        'source'   => $entity == $object ? NULL : $object,
        'settings' => $settings,
      ];

      // We may have a Media entity, etc.
      $output = self::fromContent($options);
    }

    // Final URI check.
    $uri = $uri ?: Uri::fromImage($output, $settings);

    if ($uri) {
      $blazies->set('image.uri', $uri);
    }

    return $output;
  }

  /**
   * Returns the image item from any sources, if available.
   *
   * This block is a bit scary yet it is a more organized way to extract Image
   * item from various sources in tandem with custom settings.image previously
   * scattered with if-else. This has saved more than 60 lines, and two methods:
   * ::fromMedia(), already gone. Can be better.
   *
   * @param array $options
   *   The given options.
   * @param string|null $name
   *   The given name.
   *
   * @return object|null
   *   The ImageItem or null.
   *
   * @todo add union types at 4.x:
   * string|null $name.
   */
  public static function fromContent(
    array $options,
    $name = NULL,
  ): ?object {
    $settings = Internals::toHashtag($options);
    $blazies  = Internals::getBlazies($settings);
    $poster   = $settings['image'] ?? NULL;
    $poster   = $blazies->get('field.formatter.image', $poster);
    $name     = $name ?: $poster;

    // If poster is not defined, use the source_field or thumbnail property.
    // Title is NULL from thumbnail, likely core bug, so use source.
    if (!$name && $source = $blazies->get('media.source')) {
      $name = $source == 'image' ? $blazies->get('media.source_field') : 'thumbnail';
    }

    $func = function ($key, $property) use ($options) {
      $object = ($options[$key] ?? NULL);
      if ($object instanceof ContentEntityInterface
        && $object->hasField($property)) {
        $item = $object->get($property)->first();
        $valid = self::isImage($item);

        // Media embedded inside Paragraph item as defined by settings.image,
        // basically drilling down nested entities here to find the gold.
        if ($item) {
          if (!$valid && $entity = ($item->entity ?? NULL)) {
            if ($entity instanceof ContentEntityInterface
              && $entity->hasField('thumbnail')) {
              $item = $entity->get('thumbnail')->first();
            }
          }

          // For Remote video, it has meaningful label from OEmbed, OOTB.
          // @phpstan does not get alias self::isImage().
          if ($item instanceof ImageItem && property_exists($item, 'title')) {
            if (trim($item->title ?? '') == '') {
              $item->title = $object->label();
            }
          }
        }

        // @phpstan does not get alias self::isImage().
        return $item instanceof ImageItem ? $item : NULL;
      }
      return NULL;
    };

    // \Drupal\paragraphs\Entity\Paragraph, Media, Node, etc.
    $item = $func('entity', $name) ?: $func('source', $name);
    $item = $name ? $item : NULL;
    if (!$item) {
      $item = $func('entity', 'thumbnail') ?: $func('source', 'thumbnail');
    }

    return $item;
  }

  /**
   * Returns TRUE if an ImageItem with lazy check upstream.
   *
   * @param \Drupal\image\Plugin\Field\FieldType\ImageItem|null $item
   *   The given image item or null.
   *
   * @return bool
   *   True if an ImageItem.
   *
   * @todo add union types at 4.x:
   * ImageItem|null $item.
   */
  public static function isImage($item): bool {
    return $item instanceof ImageItem;
  }

  /**
   * Checks if we have image item with lazy check upstream.
   *
   * @param array|object|null $item
   *   The given image item or null.
   *
   * @return bool
   *   True if an ImageItem or an array having the image item.
   *
   * @todo add union types at 4.x:
   * array|object|null $item.
   */
  public static function isValid($item): bool {
    if ($item) {
      $item = is_array($item) ? Internals::toHashtag($item, 'item') : $item;
      if ($item instanceof ImageItem) {
        return TRUE;
      }

      if (is_object($item)) {
        // Fake image item has URI, the real one has alt and target_id.
        return isset($item->uri) || (isset($item->target_id) && isset($item->alt));
      }
    }
    return FALSE;
  }

  /**
   * Returns the image item from any sources, if available.
   *
   * PHP 7.2 accepts object. D8 >= PHP 7.3. Not good for D7 backport.
   *
   * @param array|\stdClass|\Drupal\Core\Field\Plugin\Field\FieldType\EntityReferenceItemInterface|\Drupal\image\Plugin\Field\FieldType\ImageItem|\Drupal\Core\Entity\EntityInterface|null $item
   *   The given image item, entity, array or null.
   * @param array $options
   *   The given options.
   * @param string $name
   *   The field name.
   *
   * @return object|null
   *   True ImageItem or null.
   *
   * @todo refine and recheck other sources in case a regression.
   * @todo add union types at 4.x:
   * array|stdClass|EntityInterface|EntityReferenceItemInterface|
   * ImageItem|null $item = NULL,
   * string|null $name.
   */
  public static function item(
    $item = NULL,
    array $options = [],
    $name = NULL,
  ): ?object {
    return self::isValid($item) ? $item : self::fromContent($options, $name);
  }

  /**
   * Prepares URLs, placeholder, and dimensions for an individual image.
   *
   * Respects a few scenarios:
   * 1. Blazy Filter or unmanaged file with/ without valid URI.
   * 2. Hand-coded image_url with/ without valid URI.
   * 3. Respects first_uri without image_url such as colorbox/zoom-like.
   * 4. File API via field formatters or Views fields/ styles with valid URI.
   * If we have a valid URI, provides the correct image URL.
   * Otherwise leave it as is, likely hotlinking to external/ sister sites.
   * Hence URI validity is not crucial in regards to anything but #4.
   * The image will fail silently at any rate given non-expected URI.
   *
   * @param array $settings
   *   The modified settings.
   * @param \stdClass|\Drupal\image\Plugin\Field\FieldType\ImageItem|null $item
   *   The image item or null.
   * @param string|null $uri
   *   The image uri or null.
   *
   * @requires CheckItem::unstyled()
   * @requires self::styles()
   *
   * @todo add union types at 4.x:
   * stdClass|ImageItem|null $item
   * string|null $uri.
   */
  public static function prepare(
    array &$settings,
    $item = NULL,
    $uri = NULL,
  ): void {
    // Problems: the audio/ video poster is not synced. The root cause, local
    // media is not directly managed by theme_blazy() aka outside the workflow,
    // it is an embedded field. The correct solution is to call this method
    // before working with local media. They won't re-enter this method again.
    // @todo recheck $blazies = $settings['blazies']->reset($settings);
    $blazies = Internals::getBlazies($settings)->reset($settings);
    $uri = $uri ?: $blazies->get('image.uri');

    // Bailout if no URI.
    if (!$uri) {
      return;
    }

    // Provides original image dimensions.
    self::dimensions($settings, $item, $uri, FALSE);

    // Provides transformed image dimensions regardless unstyled so to have
    // correct dimensions at lightboxes, thumbnails, etc.
    self::transformed($settings, $uri);

    // Provides ResponsiveImage dimensions and styles, if any.
    ResponsiveImage::transformed($settings);

    // Provides SVG dimensions, if any.
    BlazySvg::dimensions($settings, $uri);
    Internals::tokenize($blazies);
  }

  /**
   * Checks for Image styles at container level once, except for multi-styles.
   *
   * @todo deprecate and remove for BlazyManager::imageStyles().
   *
   * @param array $settings
   *   The modified settings.
   * @param bool $multiple
   *   Whether multiple or single image styles.
   *
   * @todo move callers into DI at D11.
   */
  public static function styles(
    array &$settings,
    bool $multiple = FALSE,
  ): void {
    if ($manager = Internals::blazy()) {
      $manager->imageStyles($settings, $multiple);
    }
  }

  /**
   * A wrapper for ImageStyle::transformDimensions().
   *
   * @param object|null $style
   *   The given image style.
   * @param array|\Drupal\blazy\BlazySettings $config
   *   The data config: width, height, and uri, or $blazies as config source.
   * @param string|null $uri
   *   The optional URI if differs from main image, such as thumbnail URI.
   *
   * @todo recheck if NULL $style id relevant here.
   * @todo add union types at 4.x:
   * object|null $style,
   * array|BlazySettings $config,
   * string|null $uri = NULL,
   */
  public static function transformDimensions(
    $style,
    $config,
    $uri = NULL,
  ): array {
    $fluid  = FALSE;
    $ratios = [];

    // Default non-API source:
    if (is_array($config)) {
      $uri    = $uri ?: ($config['uri'] ?? '');
      $width  = $config['width'] ?? NULL;
      $height = $config['height'] ?? NULL;
    }
    // A convenient API source, must be original sizes:
    else {
      $fluid  = $config->is('fluid');
      $ratios = $config->get('css.ratio');
      $uri    = $uri ?: ($config->get('image.uri') ?: $config->get('first.uri'));
      $width  = $config->get('image.original.width') ?: $config->get('first.width');
      $height = $config->get('image.original.height') ?: $config->get('first.height');
    }

    $dim = ['width' => $width, 'height' => $height];

    // Funnily $uri is ignored at all core image effects.
    if ($style instanceof ImageStyleInterface) {
      $style->transformDimensions($dim, $uri);
    }

    // Sometimes they are string, cast them integer to reduce JS logic.
    self::toInt($dim, 'width', 'height');

    if ($fluid) {
      $info = $dim;
      $info['ratios'] = $ratios;
      $fluid = Ratio::fluid($info);
    }

    // Keys here are hard-coded, so to be inherited by children as intended.
    // See self::dimensions().
    return [
      'width'  => $dim['width'],
      'height' => $dim['height'],
      'ratio'  => Ratio::compute($dim),
      'fluid'  => $fluid,
    ];
  }

  /**
   * Converts dimensions to integer unless empty.
   */
  private static function toInt(array &$data, $width, $height): void {
    $data[$width] = empty($data[$width]) ? NULL : (int) $data[$width];
    $data[$height] = empty($data[$height]) ? NULL : (int) $data[$height];
  }

  /**
   * Returns data to provide fake image item of file entity via ImageFactory.
   *
   * @todo deprecate and remove ImageItem, fake or real, at 3.x. No longer neccessary with
   * $blazies as object as planned at BlazyMedia since 2.6.
   *
   * @param \Drupal\blazy\BlazySettings $blazies
   *   The BlazySettings instance.
   * @param \Drupal\file\FileInterface|null $file
   *   The file instance or null.
   *
   * @return \stdClass|null
   *   The fake image item, or null.
   *
   * @todo add union types at 4.x:
   * FileInterface|null $file.
   */
  private static function fakeFromFactory(
    BlazySettings $blazies,
    $file,
  ): ?object {
    $factory = Internals::service('image.factory');

    if (!$factory && !$file instanceof FileInterface) {
      return NULL;
    }

    $alt = $blazies->get('image.alt');
    $title = $blazies->get('image.title');

    if ($data = self::fromFile($file, $factory, $alt, $title)) {
      $dims = ['width' => $data['width'], 'height' => $data['height']];

      // @todo move it out of here for self::toArray():
      $blazies->set('image', $data, TRUE)
        ->set('image.original', $dims, TRUE);

      // @todo deprecate and remove this pingpong at 3.x:
      $item = $blazies->toImage($data);
      $blazies->set('image.item', $item);

      return $item;
    }

    return NULL;
  }

  /**
   * Returns data to provide fake image item of file entity via ImageFactory.
   *
   * @param \Drupal\file\FileInterface $file
   *   The file instance.
   * @param \Drupal\Core\Image\ImageFactory $factory
   *   The image factory instance.
   * @param string|null $alt
   *   The image alt.
   * @param string|null $title
   *   The image title.
   *
   * @return array
   *   The image definition.
   *
   * @todo add union types at 4.x:
   * string|null $alt = NULL,
   * string|null $title = NULL,
   */
  private static function fromFile(
    FileInterface $file,
    ImageFactory $factory,
    $alt = NULL,
    $title = NULL,
  ): array {
    // Might be a video/ audio file URI, not just image.
    // @todo recheck not available beyond formatters, such as View Fields:
    // $item = $entity->_referringItem;
    $check = $file->getFileUri();

    if ($image = $factory->get($check)) {
      [$type] = explode('/', $file->getMimeType(), 2);

      // Including image/svg+xml.
      // ALT and TITLE might be hand-coded from BlazyFilter, and so meaningful.
      // @todo recheck && $image->isValid() and put it back if any issues.
      // @todo figure out some SVG invalid when accessed from non-formatters
      // like BlazyViewsFieldFile.
      if ($type == 'image') {
        $name = $file->getFilename();
        return [
          'uri'       => $file->getFileUri(),
          'target_id' => $file->id(),
          'alt'       => $alt ?: $name,
          'title'     => $title ?: '',
          'width'     => $image->getWidth(),
          'height'    => $image->getHeight(),
          'type'      => 'image',
          'entity'    => $file,
        ];
      }
    }
    return [];
  }

  /**
   * Returns the image style if it contains crop effect.
   *
   * @param \Drupal\image\ImageStyleInterface $style
   *   The image style to check for.
   *
   * @return \Drupal\image\ImageStyleInterface|null
   *   Returns the image style instance if it contains crop effect, else NULL.
   */
  private static function getCrop(ImageStyleInterface $style): ?ImageStyleInterface {
    $id = $style->id();

    if (!isset(static::$crop[$id])) {
      $output = NULL;

      foreach ($style->getEffects() as $effect) {
        if (strpos($effect->getPluginId(), 'crop') !== FALSE) {
          $output = $style;
          break;
        }
      }
      static::$crop[$id] = $output;
    }
    return static::$crop[$id];
  }

  /**
   * Extracts common data from a fake or real image item object.
   *
   * The best reason to remove ImageItem references is this pingpong.
   * Plan for 3.x:
   *   - Keep fake image item as array, no need to be an object.
   *   - Convert real ImageItem to an array when found.
   *   - Store both as just array into blazies.image.
   *
   * Since 2.17, a reliance on ImageItem has been gradually removed like seen at
   * Lightbox, at least made a fallback, no longer the dominance.
   */
  public static function toArray($item): array {
    $data = [];

    // A fake ImageItem has a uri and target_id.
    if (isset($item->uri)) {
      return (array) $item;
    }
    // A real ImageItem has a target_id, but no URI.
    elseif (isset($item->target_id)) {
      $uri = Uri::fromImage($item);
      $data = ['uri' => $uri];

      foreach (BlazyDefault::imageProperties() as $key) {
        if (isset($item->{$key})) {
          $data[$key] = $item->{$key};
        }
      }
    }

    return $data;
  }

  /**
   * Provides result of self::transformDimensions().
   *
   * Image styles were provided once at the container level, but not dimensions
   * which may require URIs at item level. Previously these are scattered around
   * as required, now called once for all. Nothing loaded if not so configured.
   * Since Blazy:2.9, image style entity is loaded once at container level,
   * but might still be needed for adopted Image formatter by a Views style.
   *
   * @todo since done at container, it might also truble the unstyled per URI.
   * @todo deprecate and remove `image` check after another check. Was needed to be undefined
   * to not conflict with Responsive image last time, till required. Also image
   * may be set once if cropped at self::cropDimensions().
   * URI is not available at container level, except for the first,
   * or when preload option is enabled, unless enforced in the far future.
   *
   * Requires self::styles().
   *
   * @param array $settings
   *   The modified settings.
   * @param string $uri
   *   The image uri.
   */
  private static function transformed(array &$settings, string $uri): void {
    $blazies = Internals::getBlazies($settings);

    // GIF, etc. can be converted. We'll refine SVG, external URL down below.
    // For now, only data URI is out of question.
    if (!$blazies->is('data_uri')) {
      self::transformedInternal($settings, $uri);
    }

    // External and unstyled image urls.
    if (!$blazies->get('image.url')) {
      $style = $blazies->get('image.style');
      $url = Url::fromAny($settings, $style, $uri);
      $blazies->set('image.url', $url);
    }
  }

  /**
   * Provides result of self::transformDimensions() for internal urls.
   *
   * @param array $settings
   *   The modified settings.
   * @param string $uri
   *   The image uri.
   */
  private static function transformedInternal(array &$settings, string $uri): void {
    $blazies = Internals::getBlazies($settings);
    foreach (BlazyDefault::imageStyles() as $key) {
      if ($style = $blazies->get($key . '.style')) {

        // @todo recheck if to disable for external URL upstream.
        $data = self::transformDimensions($style, $blazies, $uri);
        $blazies->set($key, $data, TRUE);

        // SVG and external don't convert, exclude them.
        if (!$blazies->is('svg') && !$blazies->is('external')) {
          $url = Url::fromAny($settings, $style, $uri);
          $blazies->set($key . '.url', $url);
        }

        // To avoid double checks.
        if ($key == 'image') {
          $blazies->set('cache.metadata.tags', $style->getCacheTags(), TRUE);
        }
      }
    }
  }

}
