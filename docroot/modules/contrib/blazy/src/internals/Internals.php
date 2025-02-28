<?php

namespace Drupal\blazy\internals;

use Drupal\blazy\Blazy;
use Drupal\blazy\BlazyDefault;
use Drupal\blazy\BlazySettings;
use Drupal\blazy\Media\BlazyFile;
use Drupal\blazy\Media\BlazyImage;
use Drupal\blazy\Media\Provider\Youtube;
use Drupal\blazy\Theme\Grid;
use Drupal\blazy\Utility\Check;
use Drupal\blazy\Utility\CheckItem;
use Drupal\blazy\Utility\Markdown;
use Drupal\blazy\Utility\Path;
use Drupal\blazy\Utility\Sanitize;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Provides internal kitchen-skink non-reusable blazy utilities.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module.
 */
class Internals {

  /**
   * The data URI text.
   */
  const DATA_TEXT = 'data:text/plain;base64,';

  /**
   * The blazy HTML ID.
   *
   * @var int|null
   */
  protected static $blazyId;

  /**
   * Alias for base_path() for easy removal.
   *
   * @todo replace base_path() if any replacement by D11.
   */
  public static function basePath(): ?string {
    return \base_path() ?: '';
  }

  /**
   * Provides common content settings.
   */
  public static function contently(array &$settings): void {
    $blazies = $settings['blazies'];

    // Disable all lazy stuffs since we got a brick here.
    // @todo recheck any misses, and refine overlaps.
    $settings['media_switch'] = $settings['ratio'] = '';
    $blazies->set('is.unlazy', TRUE)
      ->set('lazy.html', FALSE)
      ->set('media.type', '')
      ->set('placeholder', [])
      ->set('switch', '')
      ->set('use.bg', FALSE)
      ->set('use.blur', FALSE)
      ->set('use.content', TRUE)
      ->set('use.loader', FALSE)
      ->set('use.player', FALSE);

    // @todo remove dup is for use at 3.x.
    $blazies->set('is.bg', FALSE)
      ->set('is.blur', FALSE)
      ->set('is.player', FALSE)
      ->set('is.rendered', TRUE);
  }

  /**
   * Returns the expected/ corrected input URL.
   *
   * @param string $input
   *   The given url.
   *
   * @return string
   *   The input url.
   */
  public static function correct($input): ?string {
    // If you bang your head around why suddenly Instagram failed, this is it.
    // Only relevant for VEF, not core, in case ::toEmbedUrl() is by-passed:
    if ($input && strpos($input, '//instagram') !== FALSE) {
      $input = str_replace('//instagram', '//www.instagram', $input);
    }
    return $input;
  }

  /**
   * Returns TRUE if the link has empty title, or just plain URL or text.
   */
  public static function emptyOrPlainTextLink(array $link): bool {
    $empty = FALSE;
    if ($title = $link['#title'] ?? NULL) {
      // @todo php 8: str_starts_with($title, '/');
      $length = strlen('/');
      $empty = substr($title, 0, $length) === '/' || strpos($title, 'http') !== FALSE;
    }

    if ($empty ||
      isset($link['#plain_text']) ||
      isset($link['#context']['value'])) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Checks if a provider can not use aspect ratio due to anti-mainstream sizes.
   */
  public static function irrational($provider): bool {
    return in_array($provider ?: 'x', [
      'd500px',
      'flickr',
      'instagram',
      'oembed:instagram',
      'pinterest',
      'twitter',
    ]);
  }

  /**
   * Disables linkable Pinterest, Twitter, etc.
   *
   * @todo refine or excludes other providers that should not be linked.
   */
  public static function linkable($blazies): bool {
    if ($provider = $blazies->get('media.provider')) {
      if (self::irrational($provider) || in_array($provider, ['facebook'])) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Provider sometimes NULL when called by sub-modules, not Blazy.
   *
   * @fixme somewhere else.
   */
  public static function provider($blazies, $provider = NULL): ?string {
    if (!$provider && $input = $blazies->get('media.input_url')) {
      $provider = str_ireplace(['www.', '.com'], '', parse_url($input, PHP_URL_HOST));
    }
    return $provider;
  }

  /**
   * Alias for Youtube::fromEmbed().
   */
  public static function youtube($input): ?string {
    return Youtube::fromEmbed($input);
  }

  /**
   * Returns the highest views rows, or field items count to determine gallery.
   *
   * Sliders may trick count 100 into just 2 for their magic chunk trick.
   */
  public static function count($blazies, $default = 0): int {
    $field = $blazies->get('total', 0) ?: $blazies->get('count', 1);
    $views = $blazies->get('view.count', 0);
    $count = $views > $field ? $views : $field;
    $total = $count > $default ? $count : $default;

    // Store it in an undisturbed location.
    $blazies->set('item.count', $total);
    return $total;
  }

  /**
   * Returns a message if access to view the entity is denied.
   */
  public static function denied($entity): array {
    if (!$entity instanceof EntityInterface) {
      return [];
    }

    if (!$entity->access('view')) {
      $parameters = [
        '@label' => $entity->getEntityType()->getSingularLabel(),
        '@id' => $entity->id(),
        '@langcode' => $entity->language()->getId(),
        '@title' => $entity->label(),
      ];
      $restricted_access_label = $entity->access('view label')
       ? new FormattableMarkup('@label @id (@title)', $parameters)
       : new FormattableMarkup('@label @id', $parameters);
      return ['#markup' => $restricted_access_label];
    }
    return [];
  }

  /**
   * Returns the trusted HTML ID of a single instance.
   */
  public static function getHtmlId($namespace = 'blazy', $id = ''): string {
    if (!isset(static::$blazyId)) {
      static::$blazyId = 0;
    }

    // Do not use dynamic Html::getUniqueId, otherwise broken AJAX.
    $id = empty($id) ? ($namespace . '-' . ++static::$blazyId) : $id;
    return Html::getId($id);
  }

  /**
   * Alias for Path::getLibrariesPath().
   */
  public static function getLibrariesPath($name, $base_path = FALSE): ?string {
    return Path::getLibrariesPath($name, $base_path);
  }

  /**
   * Alias for Path::getPath().
   */
  public static function getPath($type, $name, $absolute = FALSE): ?string {
    return Path::getPath($type, $name, $absolute);
  }

  /**
   * Returns minimal View data.
   */
  public static function getViewFieldData($view): array {
    $data = $names = [];
    foreach ($view->field as $field_name => $field) {
      if ($options = $field->options ?? []) {
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
   */
  public static function getViewLimit($blazies): int {
    $data = $blazies->get('view.data', []);
    $name = $blazies->get('field.name', 'x');
    return $data[$name]['limit'] ?? 0;
  }

  /**
   * Import a config entity, and save it into database.
   *
   * @param array $options
   *   Containing:
   *     - module, the module name where config to be imported is stored.
   *     - basename, file name without .yml extension: slick.optionset.nav, etc.
   *     - folder, whether install, or optional.
   */
  public static function import(array $options): void {
    $options = $options + ['folder' => 'install'];

    [
      'module' => $module,
      'basename' => $basename,
      'folder' => $folder,
    ] = $options;

    $path = self::getPath('module', $module);
    $config_path = sprintf('%s/config/%s/%s.yml', $path, $folder, $basename);

    if ($data = Yaml::parseFile($config_path)) {
      \Drupal::configFactory()->getEditable($basename)
        ->setData($data)
        ->save(TRUE);
    }
  }

  /**
   * Checks if it is an SVG.
   */
  public static function isSvg($uri): bool {
    return BlazyFile::isSvg($uri);
  }

  /**
   * Disable lazyload as required.
   *
   * The following will disable lazyload:
   * - if loading:slider (LCP) is chosen for initial slide, normally delta 0.
   * - If unlazy: globally disabled via `No JavaScript` option.
   * - If static: CK Editor/ preview mode, AMP, and sandboxed mode.
   */
  public static function isUnlazy($blazies): bool {
    return $blazies->is('unlazy')
      || $blazies->is('static')
      || $blazies->is('slider') && $blazies->is('initial');
  }

  /**
   * Checks if it is a video.
   */
  public static function isVideo($blazies): bool {
    if ($blazies->get('media.input_url')) {
      $type = $blazies->get('media.resource.type') ?: $blazies->get('media.type');
      return $type == 'video';
    }
    return FALSE;
  }

  /**
   * Returns a entity object by a property.
   *
   * @todo remove for BlazyInterface::loadByProperty().
   */
  public static function loadByProperty($property, $value, $type, $manager = NULL): ?object {
    $manager = $manager ?: self::service('blazy.manager');
    return $manager ? $manager->loadByProperty($property, $value, $type) : NULL;
  }

  /**
   * Returns a entity object by a UUID.
   *
   * @todo remove for BlazyInterface::loadByUuid().
   */
  public static function loadByUuid($uuid, $type, $manager = NULL): ?object {
    $manager = $manager ?: self::service('blazy.manager');
    return $manager ? $manager->loadByUuid($uuid, $type) : NULL;
  }

  /**
   * Returns markdown.
   */
  public static function markdown($string, $help = TRUE, $sanitize = TRUE): string {
    return Markdown::parse($string, $help, $sanitize);
  }

  /**
   * Prepares the essential settings, URI, delta, cache , etc.
   */
  public static function prepare(array &$settings, $item, $called = FALSE): void {
    CheckItem::essentials($settings, $item, $called);
    CheckItem::insanity($settings);
  }

  /**
   * Blazy is prepared with an URI.
   */
  public static function prepared(array &$settings, $item): void {
    BlazyImage::prepare($settings, $item);
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

    $parent = $parentsets['blazies'];
    $child  = $childsets['blazies'];

    if ($bg = $parentsets['background'] ?? FALSE) {
      $parent->set('is.bg', $bg);
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
   * @todo refine to separate container from item level. At least move grid out.
   */
  public static function preSettings(array &$settings, $root = TRUE): void {
    $blazies = self::verify($settings);

    // Checks for basic features, here for both formatters and views fields.
    // To detect available media bundles from views field when
    // BlazyEntity::prepare() was called too early before media data set.
    // @todo move it back after initialized after both are synced.
    Check::container($settings);

    if ($blazies->was('initialized')) {
      return;
    }

    // Checks for lightboxes.
    Check::lightboxes($settings);

    // Checks for grids.
    if ($root) {
      Check::grids($settings);
    }

    // Checks for Image styles, excluding Responsive image.
    BlazyImage::styles($settings);

    // Marks it processed.
    $blazies->set('was.initialized', TRUE);
  }

  /**
   * Modifies the common UI settings inherited down to each item.
   */
  public static function postSettings(array &$settings): void {
    // Failsafe, might be called directly at ::attach() outside the workflow.
    $blazies = self::verify($settings);
    if (!$blazies->was('initialized')) {
      self::preSettings($settings);
    }
  }

  /**
   * Reset the BlazySettings per item to have unique URI, delta, style, etc.
   */
  public static function reset(array &$settings, $key = 'blazies', array $defaults = []): BlazySettings {
    // Other implementors should verify the $key prior to calling this.
    self::verify($settings, $key, $defaults);

    // The settings instance must be unique per item.
    $config = &$settings[$key];
    if (!$config->was('reset')) {
      $config->reset($settings, $key);
      $config->set('was.reset', TRUE);
    }

    return $config;
  }

  /**
   * Returns the cross-compat D8 ~ D10 app root.
   */
  public static function root($container) {
    return version_compare(\Drupal::VERSION, '9.0', '<')
      ? $container->get('app.root') : $container->getParameter('app.root');
  }

  /**
   * Returns a wrapper to pass tests, or DI where adding params is troublesome.
   */
  public static function service($service) {
    return \Drupal::hasService($service) ? \Drupal::service($service) : NULL;
  }

  /**
   * Alias for BlazySettings().
   */
  public static function settings(array $data = []): BlazySettings {
    return new BlazySettings($data);
  }

  /**
   * Returns the common content item.
   */
  public static function toHtml($content, $tag = 'div', $class = NULL): array {
    if ($class) {
      $attributes = is_array($class) ? $class : ['class' => [$class]];
      $output = [
        '#type' => 'html_tag',
        '#tag' => $tag,
        '#attributes' => $attributes,
      ];

      // Allows empty IFRAME, etc. tags.
      if (!is_null($content)) {
        $content = is_string($content) ? ['#markup' => $content] : $content;
        $output['content'] = $content;
      }
      return $output;
    }
    return $content ?: [];
  }

  /**
   * Sets a token based on media or image url.
   */
  public static function tokenize($blazies): void {
    $url = $blazies->get('media.embed_url') ?: $blazies->get('image.url');
    $uri = $blazies->get('image.uri');
    $token = substr(md5($uri . $url), 0, 11);

    self::scriptable($blazies);

    $blazies->set('media.token', 'b-' . $token);
  }

  /**
   * Alias for Grid::toNativeGrid().
   */
  public static function toNativeGrid(array &$settings): void {
    Grid::toNativeGrid($settings);
  }

  /**
   * Modifies settings to support iframes.
   */
  public static function toPlayable($blazies, $src = NULL, $sanitized = FALSE): BlazySettings {
    if ($src) {
      if (!$sanitized) {
        $src = Sanitize::url($src);
        $sanitized = TRUE;
      }

      $blazies->set('media.embed_url', $src)
        ->set('media.escaped', $sanitized);
    }

    // @todo remove is.rendered for use.content at 3.x:
    $blazies->set('is.rendered', FALSE);

    return $blazies->set('is.iframeable', TRUE)
      ->set('is.playable', TRUE)
      ->set('is.multimedia', TRUE)
      ->set('use.content', FALSE)
      ->set('libs.media', TRUE);
  }

  /**
   * Verify `blazies` exists, in case accessed outside the workflow.
   */
  public static function verify(array &$settings, $key = 'blazies', array $defaults = []): BlazySettings {
    if (!isset($settings[$key])) {
      $settings += $defaults ?: Blazy::init();

      // A failsafe for edge cases:
      if (!isset($settings[$key])) {
        $settings[$key] = self::settings();
      }
    }

    // In case overriden above without extending self::init().
    $settings += Blazy::init();
    return $settings[$key];
  }

  /**
   * A helper to gradually convert things to #things to avoid render error.
   */
  public static function hashtag(array &$data, $key = 'settings', $unset = FALSE): void {
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
   * A helper to gradually migrate sub-modules content into theme_blazy().
   */
  public static function toContent(
    array &$data,
    $unset = FALSE,
    array $keys = ['content', 'box', 'slide']
  ): array {
    $result = [];
    foreach ($keys as $key) {
      $value = $data[$key] ?? $data["#$key"] ?? [];
      if ($value) {
        $result = $value;
        break;
      }
      if ($unset) {
        unset($data[$key]);
      }
    }
    return $result;
  }

  /**
   * A helper to gradually convert things to #things to avoid render error.
   */
  public static function toHashtag(array $data, $key = 'settings', $default = []) {
    $result = $data["#$key"] ?? $data[$key] ?? $default;
    if (!$result && $key == 'settings') {
      $result = $data["#blazy"] ?? $default;
    }
    return $result;
  }

  /**
   * Sets Instagram script if so configured, for oembed:instagram, not VEF.
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
