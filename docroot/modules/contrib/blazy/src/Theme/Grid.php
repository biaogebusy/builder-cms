<?php

namespace Drupal\blazy\Theme;

use Drupal\blazy\Blazy;
use Drupal\blazy\internals\Internals;
use Drupal\blazy\Utility\Arrays;
use Drupal\blazy\Utility\Check;

/**
 * Provides grid utilities.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module ecosystem.
 */
class Grid {

  /**
   * Returns items wrapped by theme_item_list(), can be a grid, or plain list.
   *
   * @param array|\Generator $items
   *   The grid items, can be plain array or generator.
   * @param array $settings
   *   The given settings.
   *
   * @return array
   *   The modified array of grid items.
   */
  public static function build($items, array $settings): array {
    // Might be called outside the workflow like Slick/ Splide list builders.
    $blazies = Internals::verify($settings);

    // If the workflow is by-passed, by calling this directly, re-check grids.
    // If grid chunks with destroyed un(slick|splide), refresh with libraries.
    $refresh = $blazies->is('grid_refresh');
    if (!$blazies->get('namespace') || $refresh) {
      Check::grids($settings);
    }

    // Might be called outside Blazy workflows, allows altering settings once.
    $attachments = $attrs = [];
    if ($manager = Internals::service('blazy.manager')) {
      $manager->moduleHandler()->alter('blazy_settings_grid', $settings);
      $attachments = $refresh ? $manager->attach($settings) : [];
    }

    $contents = self::content($items, $settings);
    self::attributes($attrs, $settings);

    $wrappers = ['item-list--blazy'];
    if ($style = $settings['style'] ?? NULL) {
      $wrappers[] = 'item-list--blazy-' . str_replace('_', '-', $style);
    }

    return [
      '#theme'              => 'item_list',
      '#items'              => $contents,
      '#context'            => ['settings' => $settings],
      '#attributes'         => $attrs,
      '#wrapper_attributes' => ['class' => array_merge(['item-list'], $wrappers)],
      '#title'              => self::label($blazies),
      '#attached'           => $attachments,
    ];
  }

  /**
   * Provides reusable container attributes.
   */
  public static function attributes(array &$attrs, array $settings): void {
    $blazies    = $settings['blazies'];
    $gallery_id = $blazies->get('lightbox.gallery_id');
    $is_gallery = $blazies->is('gallery');
    $namespace  = $blazies->get('namespace');

    // Provides data-attributes to avoid conflict with original implementations.
    Attributes::container($attrs, $settings);

    // Provides gallery ID, although Colorbox works without it, others may not.
    // Uniqueness is not crucial as a gallery needs to work across entities.
    if ($id = $blazies->get('css.id')) {
      $id = $is_gallery && $gallery_id ? $gallery_id : $id;

      // Non-blazy may group galleries per slide like Splide or Slick.
      if ($namespace != 'blazy') {
        $id = $id . Internals::getHtmlId('-');
      }
      $attrs['id'] = $id;
    }

    // Limit to grid only, so to be usable for plain list.
    if ($blazies->is('grid')) {
      self::containerAttributes($attrs, $settings, $blazies);
    }

    // Listens to hook_blazy_settings_alter for minor alters.
    $dummy = [];
    self::checkAttributes($attrs, $dummy, $blazies, TRUE);
  }

  /**
   * Listens to signaled grid item attributes.
   *
   * Can be set via hook_blazy_settings_alter for minor alters, such as adding
   * generic .card, etc. classes without extra legs.
   */
  public static function checkAttributes(
    array &$attrs,
    array &$content_attrs,
    $blazies,
    $root = FALSE
  ): void {
    if ($root) {
      if ($attrs_alter = ($blazies->get('grid.attributes') ?: [])) {
        $attrs = Arrays::merge($attrs_alter, $attrs);
      }
    }
    else {
      if ($attrs_alter = ($blazies->get('grid.item_attributes') ?: [])) {
        $attrs = Arrays::merge($attrs_alter, $attrs);
      }

      if ($content_attrs_alter = ($blazies->get('grid.item_content_attributes') ?: [])) {
        $content_attrs = Arrays::merge($content_attrs_alter, $content_attrs);
      }
    }
  }

  /**
   * Initialize Grid at any containers with DIV > DIVs without passing contents.
   */
  public static function initGrid(array $options): array {
    $attrs   = ['class' => []];
    $count   = $options['count'] ?? 1;
    $classes = $options['classes'] ?? '';
    $gapless = $options['gapless'] ?? TRUE;
    $is_form = $options['is_form'] ?? TRUE;
    $style   = $options['style'] ?? 'nativegrid';
    $blazies = $options['blazies'] ?? Internals::settings();

    $blazies->set('count', $count)
      ->set('is.grid', TRUE)
      ->set('ui.deprecated_class', TRUE);

    $sets = [
      'grid'        => $options['grid'] ?? '6x1',
      'grid_medium' => $options['grid_medium'] ?? 2,
      'grid_small'  => $options['grid_small'] ?? 1,
      'style'       => $style,
      'blazies'     => $blazies,
    ];

    if ($style == 'nativegrid') {
      self::toNativeGrid($sets);
    }

    self::attributes($attrs, $sets);

    if (!$classes) {
      $classes = [];
    }
    else {
      if (is_string($classes)) {
        $classes = array_map('trim', explode(' ', $classes));
      }
    }

    if ($style == 'nativegrid') {
      if ($gapless) {
        $classes[] = 'is-b-gapless';
      }
      if ($is_form) {
        $attrs['class'][] = 'b-nativegrid--form';
      }
    }

    $classes = array_merge($attrs['class'], $classes);
    $attrs['class'] = array_unique(array_filter($classes));

    return ['attributes' => $attrs, 'settings' => $sets];
  }

  /**
   * Provides grid item attributes, relevant for Native Grid.
   */
  public static function itemAttributes(
    array &$attrs,
    array &$content_attrs,
    array $settings
  ): void {
    $blazies = $settings['blazies'];
    $item_class = $blazies->get('grid.item_class', 'grid');

    $classes = (array) ($attrs['class'] ?? []);
    $attrs['class'] = array_merge([$item_class], $classes);

    // Good for Bootstrap .well/ .card class, must cast or BS will reset.
    $classes = (array) ($content_attrs['class'] ?? []);
    $content_attrs['class'] = array_merge(['grid__content'], $classes);

    // Count may be set as 2 even if it is 100 by sliders for their magic trick.
    // However total, the new preserved count key, may not be set somewhere.
    // @todo use just total after sub-modules provides it to avoid this check.
    $total = Internals::count($blazies);
    $grid_count = $blazies->get('grid.count', 0);

    if ($dim = $blazies->get('grid.dimensions')) {
      $dim = (array) $dim;
      $delta = $settings['delta'] ?? $blazies->get('delta');
      if (isset($dim[$delta])) {
        $attrs['data-b-w'] = $dim[$delta]['width'];
        if ($height = $dim[$delta]['height'] ?? NULL) {
          $attrs['data-b-h'] = $height;
        }
      }
      else {
        // Supports a grid repeat for the lazy.
        // @todo use loop instead.
        $key = $delta - $grid_count;
        if (!isset($dim[$key]['width'])) {
          $key = $key - $grid_count;
        }

        $height = $dim[$key]['height'] ?? $dim[0]['height'] ?? NULL;
        $width = $dim[$key]['width'] ?? $dim[0]['width'] ?? NULL;

        if ($width && $total > $grid_count) {
          $attrs['data-b-w'] = $width;
          if ($height) {
            $attrs['data-b-h'] = $height;
          }
        }
      }
    }

    self::checkAttributes($attrs, $content_attrs, $blazies, FALSE);
  }

  /**
   * Checks if a grid expects a two-dimensional grid.
   */
  public static function isNativeGrid($grid): bool {
    return !empty($grid) && !is_numeric($grid);
  }

  /**
   * Checks if a grid uses a native grid, but expecting a masonry.
   */
  public static function isNativeGridAsMasonry(array $settings): bool {
    return !self::isNativeGrid($settings['grid'])
      && $settings['style'] == 'nativegrid';
  }

  /**
   * Extracts grid like: 4x4 4x3 2x2 2x4 2x2 2x3 2x3 4x2 4x2, or single 4x4.
   */
  public static function toDimensions($grid): array {
    $dimensions = [];
    if (self::isNativeGrid($grid)) {
      $values = array_map('trim', explode(" ", $grid));

      foreach ($values as $value) {
        $width = $value;
        $height = 0;

        // If multidimensional layout.
        if (Blazy::has($value, 'x')) {
          [$width, $height] = array_pad(array_map('trim', explode("x", $value, 2)), 2, NULL);
        }

        $dimensions[] = ['width' => (int) $width, 'height' => (int) $height];
      }
    }

    return $dimensions;
  }

  /**
   * Passes grid like: 4x4 4x3 2x2 2x4 2x2 2x3 2x3 4x2 4x2 to settings.
   */
  public static function toNativeGrid(array &$settings): void {
    if (empty($settings['grid'])) {
      return;
    }

    $blazies = $settings['blazies'];
    $grid = $settings['grid_large'] = $settings['grid'];
    if (self::isNativeGridAsMasonry($settings)) {
      $blazies->set('libs.nativegrid__masonry', TRUE);
    }

    // If Native Grid style with numeric grid, assumed non-two-dimensional.
    // @todo add supports for multiple grid_medium and grid_small.
    if ($dimensions = self::toDimensions($grid)) {
      // Prevents NestedArray from screwing up.
      // @todo remove at 3.x grid.large_dimensions for grid.dimensions.
      $blazies->set('grid.large_dimensions', $dimensions)
        ->set('grid.dimensions', (object) $dimensions)
        ->set('grid.large', $grid)
        ->set('grid.count', count($dimensions));
    }
  }

  /**
   * Limit to grid only, so to be usable for plain list.
   */
  private static function containerAttributes(array &$attrs, array $settings, $blazies): void {
    $remove  = $blazies->ui('deprecated_class', FALSE);
    $style   = $settings['style'] ?: 'grid';
    $count   = Internals::count($blazies);
    $format1 = 'b-%s';
    $format2 = 'b-count-%d';

    $attrs['class'][] = 'blazy--grid';
    $attrs['class'][] = sprintf($format1, $style);
    $attrs['class'][] = sprintf($format2, $count);

    // To remove border of the last odd item.
    if ($count % 2 != 0) {
      $attrs['class'][] = 'b-odd';
    }

    // Deprecated since 2.17, use the latest instead.
    if (!$remove) {
      $format3 = 'block-%s';
      $attrs['class'][] = sprintf($format3, $style);
    }

    // Adds common grid attributes for CSS3 column, Foundation, etc.
    // Only if using the plain grid column numbers (1 - 12).
    if ($settings['grid_large'] = $settings['grid']) {
      foreach (['small', 'medium', 'large'] as $key) {
        $value = $settings['grid_' . $key] ?? NULL;
        if ($value && is_numeric($value)) {
          $value = (int) $value;
          if ($key == 'small') {
            $nick = 'sm';
          }
          elseif ($key == 'medium') {
            $nick = 'md';
          }
          else {
            $nick = 'lg';
          }

          // Deprecated since 2.17, use the latest instead.
          if (!$remove) {
            $attrs['class'][] = $key . '-block-' . $style . '-' . $value;
          }

          $format3 = 'b-%s--%s-%d';
          $attrs['class'][] = sprintf($format3, $style, $nick, $value);
        }
      }
    }

    // If Native Grid style with numeric grid, assumed non-two-dimensional.
    if ($style == 'nativegrid') {
      $attrs['class'][] = self::isNativeGridAsMasonry($settings)
        ? 'is-b-masonry' : 'is-b-native';
    }
  }

  /**
   * Returns items wrapped by theme_item_list(), can be a grid, or plain list.
   *
   * @param array|\Generator $items
   *   The grid items, can be plain array or generator.
   * @param array $settings
   *   The given settings.
   *
   * @return array
   *   The modified array of grid items.
   */
  private static function content($items, array &$settings): array {
    $blazies    = $settings['blazies'];
    $is_grid    = $blazies->is('grid');
    $item_class = $is_grid ? 'grid' : 'blazy__item';
    $contents   = [];

    // Slick/ Splide may trick count to disable grid slides when lacking,
    // although not necessarily needed by flat grid like Blazy's.
    $count = is_array($items) ? count($items) : ($settings['count'] ?? 0);
    $count = Internals::count($blazies, $count);
    $blazies->set('count', $count)
      ->set('total', $count);

    $blazies->set('grid.item_class', $item_class);

    foreach ($items as $key => $item) {
      // @todo recheck if D9 Views outputs strings like D7, and adjust this.
      // Nobody report issues since 1.x, likely no more strings since D8+.
      if (!is_array($item)) {
        continue;
      }

      // Support non-Blazy which normally uses item_id.
      // Also update chunked grids like carousel sliders.
      $sets = Internals::toHashtag($item);
      $subs = Internals::toHashtag($item['#build'] ?? []);
      $sets = Arrays::merge($subs, $sets);
      $sets = Arrays::mergeSettings('blazies', $settings, $sets);
      $wrapper_attrs = Internals::toHashtag($item, 'attributes');
      $content_attrs = Internals::toHashtag($item, 'content_attributes');
      $image = Internals::toHashtag($item, 'item', NULL);

      $blazy = $sets['blazies'];
      $sets['delta'] = $key;

      $blazy->set('delta', $key);

      // Supports both single formatter field and complex fields such as Views.
      self::itemAttributes($wrapper_attrs, $content_attrs, $sets);

      // Remove known unused array.
      // @todo remove at/by 3.x refactors to use hashes instead.
      unset(
        $item['settings'],
        $item['attributes'],
        $item['content_attributes'],
        $item['item_attributes']
      );
      if (is_object($image)) {
        unset($item['#item'], $item['item']);
      }

      $content['content'] = $is_grid ? [
        '#theme'      => 'container',
        '#children'   => $item,
        '#attributes' => $content_attrs,
      ] : $item;

      $content['#wrapper_attributes'] = $wrapper_attrs;
      $contents[] = $content;
    }
    return $contents;
  }

  /**
   * Returns field label via Field UI, unless use.theme_field takes place.
   */
  private static function label($blazies): string {
    if (!$blazies->use('theme_field')
      && $blazies->get('field.label_display') != 'hidden') {
      return $blazies->get('field.label') ?: '';
    }
    return '';
  }

}
