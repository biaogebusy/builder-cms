<?php

namespace Drupal\blazy\Internals;

use Drupal\Component\Utility\Unicode;
use Drupal\blazy\BlazyDefault;
use Drupal\blazy\Media\Image;

/**
 * Provides internal non-reusable blazy utilities.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module.
 */
class Settings extends Initializer {

  /**
   * Implements hook_config_schema_info_alter().
   *
   * @param array $definitions
   *   The definitions being modified.
   * @param string $formatter
   *   The formatter being passed.
   * @param array $settings
   *   The settings being passed.
   */
  public static function configSchemaInfoAlter(
    array &$definitions,
    $formatter = 'blazy_base',
    array $settings = [],
  ): void {
    // Phpstan requires is_array().
    if (isset($definitions[$formatter]) && is_array($definitions[$formatter])) {
      $mappings = &$definitions[$formatter]['mapping'];
      $settings += BlazyDefault::extendedSettings();
      $settings += BlazyDefault::gridSettings();
      $settings += BlazyDefault::svgSettings();
      $settings += BlazyDefault::deprecatedSettings();
      $settings += BlazyDefault::nonBlazySettings();

      foreach ($settings as $key => $value) {
        // Seems double is ignored, and causes a missing schema, unlike float.
        $type = gettype($value);
        $type = $type == 'double' ? 'float' : $type;

        if (!isset($mappings[$key])) {
          $mappings[$key] = [];
        }
        $mappings[$key]['type'] = is_array($value) ? 'sequence' : $type;

        if (!is_array($value)) {
          $mappings[$key]['label'] = Unicode::ucfirst(str_replace('_', ' ', $key));
        }
      }
    }
  }

  /**
   * Returns the highest views rows, or field items count to determine gallery.
   *
   * Sliders may trick count 100 into just 2 for their magic chunk trick.
   *
   * @param \Drupal\blazy\BlazySettings $blazies
   *   The blazies instance.
   * @param int $default
   *   The default value.
   *
   * @return int
   *   The total amount of items.
   */
  public static function count($blazies, int $default = 0): int {
    $field = $blazies->get('total', 0) ?: $blazies->get('count', 0);
    $views = $blazies->get('view.count', 0);
    $count = $views > $field ? $views : $field;
    $total = $count > $default ? $count : $default;

    // Store it in an undisturbed location.
    $blazies->set('item.count', $total);
    return $total;
  }

  /**
   * Update count by delta option.
   *
   * @param array $settings
   *   The settings being modified.
   */
  public static function updateCountByDelta(array &$settings): void {
    $blazies  = self::getBlazies($settings);
    $by_delta = $settings['by_delta'] ?? -1;
    $total    = $blazies->total();

    if ($by_delta > -1 && $by_delta < $total) {
      $settings['count'] = 1;
      $blazies->set('count', 1)
        ->set('total', 1)
        ->set('item.count_original', $total);
    }
  }

  /**
   * Returns minimal View data.
   *
   * * @param \Drupal\views\Views|null|false $view
   *   The Views instance.
   *
   * @return array
   *   The view field data.
   */
  public static function getViewFieldData($view): array {
    $data = $names = [];

    if (!$view) {
      return [];
    }

    foreach ($view->field as $field_name => $field) {
      if ($options = $field->options ?? []) {
        // @todo figure out for phpstan w/o checkImplicitMixed.
        $options = is_array($options) ? $options : [];
        $names[] = $field_name;
        $subsets = $options['settings'] ?? [];
        $type = $options['type'] ?? 'x';

        if ($subsets) {
          if (isset($subsets['media_switch'])) {
            $data['formatters'][] = [
              'type' => $type,
              'field_name' => $field_name,
              'settings' => $subsets,
            ];
          }

          if (!empty($options['group_rows'])
            && $limit = $options['delta_limit'] ?? 0) {
            // Ensures we are in the ecosystem. Grid option is only available at
            // multi-value fields. A single value is not a concern.
            if (isset($subsets['grid_medium'])) {
              $data[$field_name]['limit'] = $limit;
              $data[$field_name]['offset'] = $options['delta_offset'] ?? 0;
              $data[$field_name]['options'] = $options;
            }
          }
        }
      }
    }

    $data['fields'] = $names;
    return $data;
  }

  /**
   * Returns delta_limit option.
   *
   * @param \Drupal\blazy\BlazySettings $blazies
   *   The blazies instance.
   *
   * @return int
   *   The view limit.
   */
  public static function getViewLimit($blazies): int {
    $data = $blazies->get('view.data', []);
    $name = $blazies->get('field.name', 'x');
    return (int) ($data[$name]['limit'] ?? 0);
  }

  /**
   * A simple wrapper for stripos().
   *
   * @param string $content
   *   The $content.
   * @param string $needle
   *   The $needle.
   *
   * @return bool
   *   Whether the content contains the needle.
   *
   * @todo use str_contains at 4.x.
   */
  public static function has($content, $needle): bool {
    if ($content && $needle = trim($needle ?: '')) {
      // stripos() won't work with diacritical signs.
      $content = strtolower($content);
      $needle  = strtolower($needle);
      return strpos($content, $needle) !== FALSE;
    }
    return FALSE;
  }

  /**
   * Disable old [data-SRC|SRCSET] lazyload for LCP or Native lazyloading.
   *
   * Since BG is not supported by Native lazy, it must stay lazyloaded, except:
   * - lcp: Hero static/slider is chosen for initial item, normally delta 0.
   * Since Blazy:3.0.17, it supports static Heroes apart from slider Heroes.
   * - static: CK Editor/ preview mode, AMP, and sandboxed mode.
   *
   * @param \Drupal\blazy\BlazySettings $blazies
   *   The blazies instance.
   *
   * @return bool
   *   Whether unlazied.
   */
  public static function isUnlazyBg($blazies): bool {
    return $blazies->is('lcp')
      || $blazies->is('static');
  }

  /**
   * Disable old [data-SRC|SRCSET] lazyload for LCP or Native lazyloading.
   *
   * The following will disable old lazyload [data-] attributes if:
   * - lcp: Hero static/slider is chosen for initial item, normally delta 0.
   * Since Blazy:3.0.17, it supports static Heroes apart from slider Heroes.
   * - unlazy: globally disabled, or by request.
   * - static: CK Editor/ preview mode, AMP, and sandboxed mode.
   *
   * @param \Drupal\blazy\BlazySettings $blazies
   *   The blazies instance.
   *
   * @return bool
   *   Whether unlazied.
   */
  public static function isUndata($blazies): bool {
    return $blazies->is('lcp')
      || $blazies->is('unlazy')
      || $blazies->is('static');
  }

  /**
   * Disable old [data-SRC|SRCSET] lazyload for LCP or Native lazyloading.
   *
   * The following will disable old lazyload [data-] attributes if:
   * - [data-SRC|SRCSET] is removed.
   * - nojs: globally disabled via `No JavaScript` option.
   *
   * @param \Drupal\blazy\BlazySettings $blazies
   *   The blazies instance.
   *
   * @return bool
   *   Whether unlazied.
   */
  public static function isUnlazy($blazies): bool {
    return self::isUndata($blazies)
      || $blazies->is('nojs');
  }

  /**
   * Prepares the essential settings, URI, delta, cache , etc.
   *
   * @param array $settings
   *   The settings being modified.
   * @param object|null $item
   *   The image item or null.
   * @param bool $called
   *   Whether has been called.
   */
  public static function prepare(array &$settings, $item, bool $called = FALSE): void {
    CheckItem::essentials($settings, $item, $called);
    CheckItem::insanity($settings);
  }

  /**
   * Blazy is prepared with an URI.
   *
   * @param array $settings
   *   The settings being modified.
   * @param object|null $item
   *   The image item or null.
   */
  public static function prepared(array &$settings, $item): void {
    Image::prepare($settings, $item);
  }

  /**
   * Preserves crucial blazy specific settings to avoid accidental overrides.
   *
   * To pass the first found Blazy formatter cherry settings into the container,
   * like Blazy Grid which lacks of options like `Media switch` or lightboxes,
   * so that when this is called at the container level, it can populate
   * lightbox gallery attributes if so configured.
   * This way at Views style, the container can have lightbox galleries without
   * extra settings, as long as `Use field template` is disabled under
   * `Style settings`, otherwise flattened out as a string.
   *
   * @param array $parentsets
   *   The parentsets being modified.
   * @param array $childsets
   *   The childsets being modified.
   *
   * @see \Drupa\blazy\BlazyManagerBase::isBlazy()
   */
  public static function preserve(array &$parentsets, array &$childsets): void {
    self::verify($parentsets);
    self::verify($childsets);

    // @todo add more formatter related settings where Views styles have none.
    $cherries = BlazyDefault::cherrySettings();

    foreach ($cherries as $key => $value) {
      $fallback = $parentsets[$key] ?? $value;
      // Ensures to respect parent formatter, or Views style if provided.
      $parentsets[$key] = isset($childsets[$key]) && empty($fallback)
        ? $childsets[$key]
        : $fallback;
    }

    /** @var \Drupal\blazy\BlazySettings $parent */
    $parent = self::getBlazies($parentsets);

    /** @var \Drupal\blazy\BlazySettings $child */
    $child = self::getBlazies($childsets);

    if ($bg = $parentsets['background'] ?? FALSE) {
      $parent->set('use.bg', $bg);
    }

    // $parent->set('first.settings', array_filter($child));
    // $parent->set('first.item_id', $child->get('item.id'));
    // Hints containers to build relevant lightbox gallery attributes.
    $childbox  = $child->get('lightbox.name');
    $parentbox = $parent->get('lightbox.name');

    // Ensures to respect parent formatter or Views style if provided.
    // The moral of this method is only if parent lacks of settings like Grid.
    // Other settings are not parents' business. Only concerns about those
    // needed by the container, e.g. LIGHTBOX for [data-LIGHTBOX-gallery].
    if ($childbox && !$parentbox) {
      // @todo use Check::lightboxes($settings);
      $optionset = $child->get('lightbox.optionset', $childbox) ?: $childbox;
      $parent->set('lightbox.name', $childbox)
        ->set($childbox, $optionset)
        ->set('is.lightbox', TRUE)
        ->set('switch', $child->get('switch'));

      // Now that we got a child lightbox, overrides parent for sure.
      $parentsets['media_switch'] = $childbox;
    }

    $parent->set('first', $child->get('first', []), TRUE)
      ->set('was.preserve', TRUE);
  }

  /**
   * Preliminary settings, normally at container/ global level.
   *
   * @param array $settings
   *   The settings being modified.
   * @param bool $root
   *   Whether a container or child element.
   *
   * @todo refine to separate container from item level. At least move grid out.
   */
  public static function preSettings(array &$settings, bool $root = TRUE): void {
    /** @var \Drupal\blazy\BlazySettings $blazies */
    $blazies = self::verify($settings);

    // Checks for basic features, here for both formatters and views fields.
    // To detect available media bundles from views field when
    // BlazyEntity::prepare() was called too early before media data set.
    // @todo move it back after initialized after both are synced.
    Check::container($settings);

    if ($blazies->was('initialized')) {
      return;
    }

    // Checks for Image styles, excluding Responsive image.
    Image::styles($settings);

    // Checks for lightboxes.
    Check::lightboxes($settings);

    // Checks for grids.
    if ($root) {
      Check::grids($settings);
    }

    // Checks for Image styles, excluding Responsive image.
    // Image::styles($settings);
    // Marks it processed.
    $blazies->set('was.initialized', TRUE);
  }

  /**
   * Modifies the common UI settings inherited down to each item.
   *
   * @param array $settings
   *   The settings being modified.
   */
  public static function postSettings(array &$settings): void {
    // Failsafe, might be called directly at ::attach() outside the workflow.
    /** @var \Drupal\blazy\BlazySettings $blazies */
    $blazies = self::verify($settings);
    if (!$blazies->was('initialized')) {
      self::preSettings($settings);
    }
  }

  /**
   * A helper to gradually convert things to #things to avoid render error.
   *
   * @param array $data
   *   The data being modified.
   * @param string $key
   *   The data key.
   * @param bool $unset
   *   Whether to unset.
   */
  public static function hashtag(
    array &$data,
    string $key = 'settings',
    bool $unset = FALSE,
  ): void {
    if (!isset($data["#$key"])) {
      $data["#$key"] = $data[$key] ?? [];
    }

    // Temporary failsafe.
    if ($unset) {
      unset($data[$key]);
    }

    $blazy = "#blazy";
    if ($key == 'settings' && isset($data[$blazy])) {
      $data["#$key"] = $data[$blazy];

      // Temporary failsafe.
      if ($unset) {
        unset($data[$blazy]);
      }
    }
  }

  /**
   * A helper to gradually convert things to #things to avoid render error.
   *
   * @param array $data
   *   The data being passed.
   * @param string $key
   *   The data key.
   * @param array|null $default
   *   The default if any.
   *
   * @return mixed
   *   The result data.
   *
   * @todo refactor avoid mixed.
   */
  public static function toHashtag(
    array $data,
    string $key = 'settings',
    $default = [],
  ) {
    $result = $data["#$key"] ?? $data[$key] ?? $default;
    if (!$result && $key == 'settings') {
      $result = $data["#blazy"] ?? $default;
    }
    return $result;
  }

  /**
   * Sets a token based on media or image url.
   *
   * @param \Drupal\blazy\BlazySettings $blazies
   *   The blazies instance.
   */
  public static function tokenize($blazies): void {
    $id    = $blazies->get('css.id', 'blazy');
    $url   = $blazies->get('media.embed_url') ?: $blazies->get('image.url', '');
    $uri   = $blazies->get('image.uri', '');
    $delta = $blazies->get('delta', 0);
    $token = substr(md5($id . $delta . $uri . $url), 0, 11);

    self::scriptable($blazies);

    $blazies->set('media.token', 'b-' . $token);
  }

  /**
   * Sets Instagram script if so configured, for oembed:instagram, not VEF.
   *
   * @param \Drupal\blazy\BlazySettings $blazies
   *   The blazies instance.
   */
  private static function scriptable($blazies): void {
    if (!$blazies->is('iframeable')) {
      if ($blazies->is('instagram') && $blazies->is('instagram_api')) {
        $blazies->set('use.instagram_api', TRUE)
          ->set('use.scripted_iframe', $blazies->use('iframe'));
      }
    }
  }

}
