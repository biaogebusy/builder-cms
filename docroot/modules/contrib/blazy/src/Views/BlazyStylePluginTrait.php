<?php

namespace Drupal\blazy\Views;

use Drupal\blazy\internals\Internals;
use Drupal\Core\Url;

@trigger_error('The ' . __NAMESPACE__ . '\BlazyStylePluginTrait is deprecated in blazy:8.x-2.17 and is removed from blazy:8.x-3.0. Use \Drupal\blazy\Views\BlazyStylePluginBase instead. See https://www.drupal.org/node/3367304', E_USER_DEPRECATED);

/**
 * A Trait common for optional views style plugins.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module. Please extend base classes intead.
 *
 * @todo move some into base classes unless clear like BlazyStyleOptionsTrait.
 * No sub-modules call this, safe to move it into BlazyStylePluginBase.
 */
trait BlazyStylePluginTrait {

  /**
   * Returns the modified renderable image_formatter to support lazyload.
   */
  protected function getImageRenderable(array &$settings, $row, $index): array {
    $image    = $this->getImageArray($row, $index, $settings['image']);
    $rendered = $image['rendered'] ?? [];
    $item     = $image['raw'] ?? NULL;

    // Supports 'group_rows' option.
    // @todo recheck if any side issues for not having raw key.
    if (!$rendered) {
      return $image;
    }

    // If the image has #item property, lazyload may work, otherwise skip.
    // This hustle is to lazyload tons of images -- grids, large galleries,
    // gridstack, mason, with multimedia/ lightboxes for free.
    /** @var \Drupal\image\Plugin\Field\FieldType\ImageItem $item */
    if ($this->isValidImageItem($item)) {
      $image['raw'] = $item;

      // Supports multiple image styles within a single view such as GridStack,
      // else fallbacks to the defined image style if available.
      if (empty($settings['image_style'])) {
        $settings['image_style'] = $rendered['#image_style'] ?? '';
      }

      // Converts image formatter for blazy to reduce complexity with CSS
      // background option, and other options, and still lazyload it.
      $theme = $rendered['#theme']
        ?? $rendered['#build'][0]['#theme']
        ?? '';

      if ($theme == 'blazy') {
        $this->withBlazyFormatter($settings, $rendered, $index);
      }
      elseif ($theme == 'image_formatter') {
        $this->withImageFormatter($settings, $rendered, $index);
      }
    }

    return $image;
  }

  /**
   * Extract image style and url from blazy image formatter.
   */
  protected function withBlazyFormatter(array &$settings, array $rendered, $index): void {
    // Pass Blazy field formatter settings into Views style plugin.
    // This allows richer contents such as multimedia/ lightbox for free.
    // Yet, ensures the Views style plugin wins over Blazy formatter,
    // such as with GridStack which may have its own breakpoints.
    $newbies = $this->manager->toHashtag($rendered['#build']);
    $blazy_settings = array_filter($newbies);
    $settings = array_merge($blazy_settings, array_filter($settings));

    // Reserves crucial blazy specific settings.
    Internals::preserve($settings, $blazy_settings);

    // Each blazy delta is always 0 within a view, this makes it gallery.
    $settings['blazies'] = $blazy_settings['blazies'];
    $settings['blazies']->set('delta', $index)
      ->set('is.gallery', !empty($settings['media_switch']));
  }

  /**
   * Extract image style and url from core image formatter.
   */
  protected function withImageFormatter(array &$settings, array $rendered, $index): void {
    $blazies = $settings['blazies'];
    // Deals with "link to content/image" by formatters.
    $url = $rendered['#url'] ?? '';

    // Checks if an object.
    if ($url instanceof Url) {
      $url = $url->setAbsolute()->toString();
    }

    // Prevent images from having absurd height when being lazyloaded.
    // Allows to disable it by _noratio such as enforced CSS background.
    $noratio = $settings['_noratio'] ?? '';
    $settings['ratio'] = $blazies->get('is.noratio', $noratio) ? '' : 'fluid';

    if (empty($settings['media_switch']) && $url) {
      $settings['media_switch'] = 'content';
      $blazies->set('switch', 'content');
    }

    $blazies->set('delta', $index)
      ->set('entity.url', $url);
  }

  /**
   * Checks if we can work with this formatter, otherwise no go if flattened.
   */
  protected function getImageArray($row, $index, $field_image): array {
    if ($field_image
      && $image = $this->getFieldRenderable($row, $index, $field_image)) {

      // Just to be sure, replace raw with the found image item.
      if ($item = $this->getImageItem($image)) {
        $image['raw'] = $item;
      }

      // Known image formatters: Blazy, Image, etc. which provides ImageItem.
      // Else dump Video embed thumbnail/video/colorbox as is.
      if ($item || isset($image['rendered'])) {
        return $image;
      }
    }
    return [];
  }

  /**
   * Get the image item to work with out of this formatter.
   *
   * All this mess is because Views may render/flatten images earlier.
   */
  protected function getImageItem($image): ?object {
    $item = NULL;

    if ($rendered = ($image['rendered'] ?? [])) {
      // Image formatter.
      $item = $rendered['#item'] ?? NULL;

      // Blazy formatter, also supports multiple, `group_rows`.
      if ($build = ($rendered['#build'] ?? [])) {
        $item = $this->manager->toHashtag($build, 'item') ?: $item;
        $item = $build[0]['#item'] ?? $item;
      }
    }

    // Don't know other reasonable formatters to work with.
    return $this->isValidImageItem($item) ? $item : NULL;
  }

  /**
   * Returns the caption elements.
   */
  protected function getCaption($index, array $settings): array {
    $view     = $this->view;
    $captions = [];
    $keys     = array_keys($view->field);
    $keys     = array_combine($keys, $keys);
    $_link    = $settings['link'] ?? NULL;
    $_title   = $settings['title'] ?? NULL;
    $_overlay = $settings['overlay'] ?? NULL;
    $_caption = $settings['caption'] ?? [];

    // Caption items: link, title, overlay, and data, anything else selected.
    $captions['title']   = $this->getFieldRendered($index, $_title, TRUE);
    $captions['link']    = $this->getFieldRendered($index, $_link);
    $captions['overlay'] = $this->getFieldRendered($index, $_overlay);

    // Exclude non-caption fields so that theme_views_view_fields() kicks in
    // and only render expected caption fields. As long as not-hidden, each
    // caption field should be wrapped with Views markups.
    if ($_caption) {
      $excludes = array_diff_assoc($keys, $_caption);
      foreach ($excludes as $field) {
        $view->field[$field]->options['exclude'] = TRUE;
      }

      if ($output = $view->rowPlugin->render($view->result[$index])) {
        $captions['data'][$index] = $output;
      }
    }

    return array_filter($captions);
  }

  /**
   * Returns the rendered layout fields, normally just string.
   */
  protected function getLayout(array &$settings, $index): void {
    $layout = $settings['layout'] ?? '';
    // Replacing useless field_NAME with its useful value.
    if (strpos($layout, 'field_') !== FALSE) {
      if ($value = $this->getField($index, $layout)) {
        $settings['layout'] = strip_tags($value);
      }
    }
  }

  /**
   * Returns TRUE if a valid image item, else FALSE.
   */
  protected function isValidImageItem($item): bool {
    return is_object($item) && (isset($item->uri) || isset($item->target_id));
  }

}
