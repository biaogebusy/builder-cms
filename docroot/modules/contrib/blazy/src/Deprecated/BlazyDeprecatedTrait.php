<?php

namespace Drupal\blazy\Deprecated;

use Drupal\blazy\BlazyAlter;
use Drupal\blazy\BlazySettings;
use Drupal\blazy\internals\Internals;
use Drupal\blazy\Theme\Attributes;
use Drupal\blazy\Theme\Grid;
use Drupal\blazy\Utility\Arrays;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Deprecated in blazy:8.x-2.0, and is removed from blazy:3.0.0.
 *
 * Static methods similar to BlazyInterface will be removed at 3.x so that Blazy
 * can be made non-static and extends BlazyBase as a non-manager alternative.
 * The `none` alternative due to more usable for internal usages. Many were
 * added and deprecated in the same version blazy:2.17, the current version.
 * Chances are nobody use them, and safe to remove them post blazy:2.17.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module, or its sub-modules.
 *
 * @todo deprecated in blazy:8.x-2.0 and is removed from blazy:3.0.0. Use the
 *   provided alternatives, if any, instead.
 * @see https://www.drupal.org/node/3103018
 */
trait BlazyDeprecatedTrait {

  /**
   * Implements hook_field_formatter_info_alter().
   *
   * @todo remove from blazy:8.x-2.1 for
   *   \Drupal\blazy\Plugin\Field\FieldFormatter\BlazyMediaFormatter.
   * @see https://www.drupal.org/node/3103018
   */
  public static function fieldFormatterInfoAlter(array &$info): void {
    // Supports optional Media Entity via VEM/VEF if available.
    $common = [
      'description' => new TranslatableMarkup('Displays lazyloaded images, or iframes, for VEF/ ME.'),
      'quickedit'   => ['editor' => 'disabled'],
      'provider'    => 'blazy',
    ];

    $info['blazy_video'] = $common + [
      'id'          => 'blazy_video',
      'label'       => new TranslatableMarkup('Blazy VEF (deprecated)'),
      'class'       => 'Drupal\blazy\Plugin\Field\FieldFormatter\BlazyVideoFormatter',
      'field_types' => ['video_embed_field'],
    ];
  }

  /**
   * Returns the cross-compat D8 ~ D10 app root.
   *
   * @todo deprecated in blazy:8.x-2.17 and is removed from blazy:3.0.0. Use
   * none instead. Only needed for D8 compat, not needed at 3.x.
   * @see https://www.drupal.org/node/3367291
   */
  public static function root($container) {
    // @trigger_error('root is deprecated in blazy:8.x-2.17 and is removed from blazy:3.0.0. Use none instead. Only needed for D8 compat, not needed at 3.x. See https://www.drupal.org/node/3367291', E_USER_DEPRECATED);
    return Internals::root($container);
  }

  /**
   * Deprecated in blazy:8.x-2.17.
   *
   * @todo deprecated in blazy:8.x-2.17 and is removed from blazy:3.0.0. Use
   * \Drupal\blazy\BlazyInterface::settings() instead.
   * @see https://www.drupal.org/node/3367291
   */
  public static function settings(array $data = []): BlazySettings {
    // @todo @trigger_error('settings is deprecated in blazy:8.x-2.17 and is removed from blazy:3.0.0. Use \Drupal\blazy\BlazyInterface::settings() instead. See https://www.drupal.org/node/3367291', E_USER_DEPRECATED);
    return Internals::settings($data);
  }

  /**
   * Deprecated in blazy:8.x-2.17.
   *
   * @todo deprecated in blazy:8.x-2.17 and is removed from blazy:3.0.0. Use
   * none instead.
   * @see https://www.drupal.org/node/3367291
   */
  public static function requestStack() {
    @trigger_error('requestStack is deprecated in blazy:8.x-2.17 and is removed from blazy:3.0.0. Use none instead. See https://www.drupal.org/node/3367291', E_USER_DEPRECATED);
    return Internals::service('request_stack');
  }

  /**
   * Deprecated in blazy:8.x-2.17.
   *
   * @todo deprecated in blazy:8.x-2.17 and is removed from blazy:3.0.0. Use
   * \Drupal\blazy\BlazyInterface::routeMatch() instead.
   * @see https://www.drupal.org/node/3367291
   */
  public static function routeMatch() {
    @trigger_error('routeMatch is deprecated in blazy:8.x-2.17 and is removed from blazy:3.0.0. Use \Drupal\blazy\BlazyInterface::routeMatch() instead. See https://www.drupal.org/node/3367291', E_USER_DEPRECATED);
    return Internals::service('current_route_match');
  }

  /**
   * Deprecated in blazy:8.x-2.17.
   *
   * @todo deprecated in blazy:8.x-2.17 and is removed from blazy:3.0.0. Use
   * none instead.
   * @see https://www.drupal.org/node/3367291
   */
  public static function streamWrapperManager() {
    @trigger_error('streamWrapperManager is deprecated in blazy:8.x-2.17 and is removed from blazy:3.0.0. Use none instead. See https://www.drupal.org/node/3367291', E_USER_DEPRECATED);
    return Internals::service('stream_wrapper_manager');
  }

  /**
   * Deprecated in blazy:8.x-2.17.
   *
   * @todo deprecated in blazy:8.x-2.17 and is removed from blazy:3.0.0. Use
   * \Drupal\blazy\BlazyInterface::service() instead.
   * @see https://www.drupal.org/node/3367291
   */
  public static function service($service) {
    // @trigger_error('service is deprecated in blazy:8.x-2.17 and is removed from blazy:3.0.0. Use \Drupal\blazy\BlazyInterface::service() instead. See https://www.drupal.org/node/3367291', E_USER_DEPRECATED);
    return Internals::service($service);
  }

  /**
   * Deprecated in blazy:8.x-2.17.
   *
   * @todo deprecated in blazy:8.x-2.17 and is removed from blazy:3.0.0. Use
   * \Drupal\blazy\BlazyInterface::attach() instead.
   * @see https://www.drupal.org/node/3367291
   */
  public static function attach(array &$variables, array $settings = []): void {
    @trigger_error('attach is deprecated in blazy:8.x-2.17 and is removed from blazy:3.0.0. Use \Drupal\blazy\BlazyInterface::attach() instead. See https://www.drupal.org/node/3367291', E_USER_DEPRECATED);
    Attributes::attach($variables, $settings);
  }

  /**
   * Deprecated in blazy:8.x-2.17.
   *
   * @todo deprecated in blazy:8.x-2.17 and is removed from blazy:3.0.0. Use
   * \Drupal\blazy\BlazyInterface::configSchemaInfoAlter() instead.
   * @see https://www.drupal.org/node/3367291
   */
  public static function configSchemaInfoAlter(
    array &$definitions,
    $formatter = 'blazy_base',
    array $settings = []
  ): void {
    // @todo @trigger_error('configSchemaInfoAlter is deprecated in blazy:8.x-2.17 and is removed from blazy:3.0.0. Use \Drupal\blazy\BlazyInterface::configSchemaInfoAlter() instead. See https://www.drupal.org/node/3367291', E_USER_DEPRECATED);
    BlazyAlter::configSchemaInfoAlter($definitions, $formatter, $settings);
  }

  /**
   * Deprecated in blazy:8.x-2.17.
   *
   * @todo deprecated in blazy:8.x-2.17 and is removed from blazy:3.0.0. Use
   * \Drupal\blazy\BlazyInterface::getHtmlId() instead.
   * @see https://www.drupal.org/node/3367291
   */
  public static function getHtmlId($namespace = 'blazy', $id = ''): string {
    // @todo @trigger_error('getHtmlId is deprecated in blazy:8.x-2.17 and is removed from blazy:3.0.0. Use \Drupal\blazy\BlazyInterface::getHtmlId() instead. See https://www.drupal.org/node/3367291', E_USER_DEPRECATED);
    return Internals::getHtmlId($namespace, $id);
  }

  /**
   * Deprecated in blazy:8.x-2.17.
   *
   * @todo deprecated in blazy:8.x-2.17 and is removed from blazy:3.0.0. Use
   * \Drupal\blazy\BlazyInterface::getLibrariesPath() instead.
   * @see https://www.drupal.org/node/3367291
   */
  public static function getLibrariesPath($name, $base_path = FALSE): ?string {
    // @todo @trigger_error('getLibrariesPath is deprecated in blazy:8.x-2.17 and is removed from blazy:3.0.0. Use \Drupal\blazy\BlazyInterface::getLibrariesPath() instead. See https://www.drupal.org/node/3367291', E_USER_DEPRECATED);
    return Internals::getLibrariesPath($name, $base_path);
  }

  /**
   * Deprecated in blazy:8.x-2.17.
   *
   * @todo deprecated in blazy:8.x-2.17 and is removed from blazy:3.0.0. Use
   * \Drupal\blazy\BlazyInterface::getPath() instead.
   * @see https://www.drupal.org/node/3367291
   */
  public static function getPath($type, $name, $absolute = FALSE): ?string {
    // @todo @trigger_error('getPath is deprecated in blazy:8.x-2.17 and is removed from blazy:3.0.0. Use \Drupal\blazy\BlazyInterface::getPath() instead. See https://www.drupal.org/node/3367291', E_USER_DEPRECATED);
    return Internals::getPath($type, $name, $absolute);
  }

  /**
   * Deprecated in blazy:8.x-2.17, added in blazy:8.x-2.16.
   *
   * @todo deprecated in blazy:8.x-2.17 and is removed from blazy:3.0.0. Use
   * \Drupal\blazy\BlazyInterface::merge() instead.
   * @see https://www.drupal.org/node/3367291
   */
  public static function merge(array $data, array $element, $key = NULL): array {
    // @todo @trigger_error('merge is deprecated in blazy:8.x-2.17 and is removed from blazy:3.0.0. Use \Drupal\blazy\BlazyInterface::merge() instead. See https://www.drupal.org/node/3367291', E_USER_DEPRECATED);
    return Arrays::merge($data, $element, $key);
  }

  /**
   * Deprecated in blazy:8.x-2.17, added in blazy:8.x-2.16.
   *
   * @todo deprecated in blazy:8.x-2.17 and is removed from blazy:3.0.0. Use
   * \Drupal\blazy\BlazyInterface::toSettings() instead.
   * @see https://www.drupal.org/node/3367291
   */
  public static function reset(array &$settings, $key = 'blazies', array $defaults = []): BlazySettings {
    // @todo @trigger_error('reset is deprecated in blazy:8.x-2.17 and is removed from blazy:3.0.0. Use \Drupal\blazy\BlazyInterface::toSettings() instead. See https://www.drupal.org/node/3367291', E_USER_DEPRECATED);
    return Internals::reset($settings, $key, $defaults);
  }

  /**
   * Deprecated in blazy:8.x-2.17, added in blazy:8.x-2.16.
   *
   * @tbd, fine to keep since it differs from BlazyInterface.
   * @todo deprecated in blazy:8.x-2.17 and is removed from blazy:3.0.0. Use
   * \Drupal\blazy\BlazyInterface::toGrid() instead.
   * @see https://www.drupal.org/node/3367291
   */
  public static function grid($items, array $settings): array {
    // @todo @trigger_error('toGrid is deprecated in blazy:8.x-2.17 and is removed from blazy:3.0.0. Use \Drupal\blazy\BlazyInterface::toGrid() instead. See https://www.drupal.org/node/3367291', E_USER_DEPRECATED);
    return Grid::build($items, $settings);
  }

  /**
   * Deprecated in blazy:8.x-2.17, added in blazy:8.x-2.16.
   *
   * @todo deprecated in blazy:8.x-2.17 and is removed from blazy:3.0.0. Use
   * \Drupal\blazy\BlazyInterface::gridAttributes() instead.
   * @see https://www.drupal.org/node/3367291
   */
  public static function gridAttributes(array &$attrs, array $settings): void {
    // @todo @trigger_error('gridAttributes is deprecated in blazy:8.x-2.17 and is removed from blazy:3.0.0. Use \Drupal\blazy\BlazyInterface::gridAttributes() instead. See https://www.drupal.org/node/3367291', E_USER_DEPRECATED);
    Grid::attributes($attrs, $settings);
  }

  /**
   * Deprecated in blazy:8.x-2.17, added in blazy:8.x-2.17.
   *
   * @todo deprecated in blazy:8.x-2.17 and is removed from blazy:3.0.0. Use
   * none instead.
   * @see https://www.drupal.org/node/3367291
   */
  public static function altTitle($blazies, $item = NULL): array {
    @trigger_error('altTitle is deprecated in blazy:8.x-2.17 and is removed from blazy:3.0.0. Use none instead. See https://www.drupal.org/node/3367291', E_USER_DEPRECATED);
    return Attributes::altTitle($blazies, $item);
  }

  /**
   * Deprecated in blazy:8.x-2.17, added in blazy:8.x-2.17.
   *
   * @todo deprecated in blazy:8.x-2.17 and is removed from blazy:3.0.0. Use
   * \Drupal\blazy\Utility\Arrays::filter() instead.
   * @see https://www.drupal.org/node/3367291
   */
  public static function arrayFilter(array $config): array {
    @trigger_error('arrayFilter is deprecated in blazy:8.x-2.17 and is removed from blazy:3.0.0. Use \Drupal\blazy\Utility\Arrays::filter() instead. See https://www.drupal.org/node/3367291', E_USER_DEPRECATED);
    return Arrays::filter($config);
  }

  /**
   * Deprecated in blazy:8.x-2.17, added in blazy:8.x-2.17.
   *
   * @todo deprecated in blazy:8.x-2.17 and is removed from blazy:3.0.0. Use
   * \Drupal\blazy\BlazyInterface::denied() instead.
   * @see https://www.drupal.org/node/3367291
   */
  public static function denied($entity): array {
    @trigger_error('denied is deprecated in blazy:8.x-2.17 and is removed from blazy:3.0.0. Use \Drupal\blazy\BlazyInterface::denied() instead. See https://www.drupal.org/node/3367291', E_USER_DEPRECATED);
    return Internals::denied($entity);
  }

  /**
   * Deprecated in blazy:8.x-2.17, added in blazy:8.x-2.17.
   *
   * @todo deprecated in blazy:8.x-2.17 and is removed from blazy:3.0.0. Use
   * \Drupal\blazy\BlazyInterface::gridCheckAttributes() instead.
   * @see https://www.drupal.org/node/3367291
   */
  public static function gridCheckAttributes(
    array &$attrs,
    array &$content_attrs,
    $blazies,
    $root = FALSE
  ): void {
    // @todo @trigger_error('gridCheckAttributes is deprecated in blazy:8.x-2.17 and is removed from blazy:3.0.0. Use \Drupal\blazy\BlazyInterface::gridCheckAttributes() instead. See https://www.drupal.org/node/3367291', E_USER_DEPRECATED);
    Grid::checkAttributes($attrs, $content_attrs, $blazies, $root);
  }

  /**
   * Deprecated in blazy:8.x-2.17, added in blazy:8.x-2.17.
   *
   * @todo deprecated in blazy:8.x-2.17 and is removed from blazy:3.0.0. Use
   * \Drupal\blazy\BlazyInterface::gridItemAttributes() instead.
   * @see https://www.drupal.org/node/3367291
   */
  public static function gridItemAttributes(
    array &$attrs,
    array &$content_attrs,
    array $settings
  ): void {
    // @todo @trigger_error('gridItemAttributes is deprecated in blazy:8.x-2.17 and is removed from blazy:3.0.0. Use \Drupal\blazy\BlazyInterface::gridItemAttributes() instead. See https://www.drupal.org/node/3367291', E_USER_DEPRECATED);
    Grid::itemAttributes($attrs, $content_attrs, $settings);
  }

  /**
   * Deprecated in blazy:8.x-2.17, added in blazy:8.x-2.17.
   *
   * @todo deprecated in blazy:8.x-2.17 and is removed from blazy:3.0.0. Use
   * \Drupal\blazy\BlazyInterface::initGrid() instead.
   * @see https://www.drupal.org/node/3367291
   */
  public static function initGrid(array $options): array {
    // @todo @trigger_error('initGrid is deprecated in blazy:8.x-2.17 and is removed from blazy:3.0.0. Use \Drupal\blazy\BlazyInterface::initGrid() instead. See https://www.drupal.org/node/3367291', E_USER_DEPRECATED);
    return Grid::initGrid($options);
  }

  /**
   * Deprecated in blazy:8.x-2.17, added in blazy:8.x-2.17.
   *
   * @todo deprecated in blazy:8.x-2.17 and is removed from blazy:3.0.0. Use
   * none instead.
   * @see https://www.drupal.org/node/3367291
   */
  public static function toNativeGrid(array &$settings): void {
    // @todo @trigger_error('toNativeGrid is deprecated in blazy:8.x-2.17 and is removed from blazy:3.0.0. Use none instead. See https://www.drupal.org/node/3367291', E_USER_DEPRECATED);
    Grid::toNativeGrid($settings);
  }

  /**
   * Deprecated in blazy:8.x-2.17, added in blazy:8.x-2.17.
   *
   * @todo deprecated in blazy:8.x-2.17 and is removed from blazy:3.0.0. Use
   * \Drupal\blazy\BlazyInterface::toHtml() instead.
   * @see https://www.drupal.org/node/3367291
   */
  public static function toHtml($content, $tag = 'div', $class = NULL): array {
    @trigger_error('toHtml is deprecated in blazy:8.x-2.17 and is removed from blazy:3.0.0. Use \Drupal\blazy\BlazyInterface::toHtml() instead. See https://www.drupal.org/node/3367291', E_USER_DEPRECATED);
    return Internals::toHtml($content, $tag, $class);
  }

  /**
   * Deprecated in blazy:8.x-2.17, added in blazy:8.x-2.17.
   *
   * @todo deprecated in blazy:8.x-2.17 and is removed from blazy:3.0.0. Use
   * \Drupal\blazy\BlazyInterface::mergeSettings() instead.
   * @see https://www.drupal.org/node/3367291
   */
  public static function mergeSettings($keys, array $defaults, array $configs): array {
    @trigger_error('mergeSettings is deprecated in blazy:8.x-2.17 and is removed from blazy:3.0.0. Use \Drupal\blazy\BlazyInterface::mergeSettings() instead. See https://www.drupal.org/node/3367291', E_USER_DEPRECATED);
    return Arrays::mergeSettings($keys, $defaults, $configs);
  }

  /**
   * Deprecated in blazy:8.x-2.17, added in blazy:8.x-2.17.
   *
   * @todo deprecated in blazy:8.x-2.17 and is removed from blazy:3.0.0. Use
   * \Drupal\blazy\BlazyInterface::loadByProperty() instead.
   * @see https://www.drupal.org/node/3367291
   */
  public static function loadByProperty($property, $value, $type, $manager = NULL): ?object {
    @trigger_error('loadByProperty is deprecated in blazy:8.x-2.17 and is removed from blazy:3.0.0. Use \Drupal\blazy\BlazyInterface::loadByProperty() instead. See https://www.drupal.org/node/3367291', E_USER_DEPRECATED);
    return Internals::loadByProperty($property, $value, $type, $manager);
  }

  /**
   * Deprecated in blazy:8.x-2.17, added in blazy:8.x-2.17.
   *
   * @todo deprecated in blazy:8.x-2.17 and is removed from blazy:3.0.0. Use
   * \Drupal\blazy\BlazyInterface::loadByUuid() instead.
   * @see https://www.drupal.org/node/3367291
   */
  public static function loadByUuid($uuid, $type, $manager = NULL): ?object {
    @trigger_error('loadByUuid is deprecated in blazy:8.x-2.17 and is removed from blazy:3.0.0. Use \Drupal\blazy\BlazyInterface::loadByUuid() instead. See https://www.drupal.org/node/3367291', E_USER_DEPRECATED);
    return Internals::loadByUuid($uuid, $type, $manager);
  }

  /**
   * Deprecated in blazy:8.x-2.17, added in blazy:8.x-2.17.
   *
   * @todo deprecated in blazy:8.x-2.17 and is removed from blazy:3.0.0. Use
   * \Drupal\blazy\BlazyInterface::markdown() instead.
   * @see https://www.drupal.org/node/3367291
   */
  public static function markdown($string, $help = TRUE): string {
    @trigger_error('markdown is deprecated in blazy:8.x-2.17 and is removed from blazy:3.0.0. Use \Drupal\blazy\BlazyInterface::markdown() instead. See https://www.drupal.org/node/3367291', E_USER_DEPRECATED);
    return Internals::markdown($string, $help);
  }

  /**
   * Deprecated in blazy:8.x-2.17, added in blazy:8.x-2.17.
   *
   * @todo deprecated in blazy:8.x-2.17 and is removed from blazy:3.0.0. Use
   * \Drupal\blazy\BlazyInterface::verifySafely() instead.
   * @see https://www.drupal.org/node/3367291
   */
  public static function verify(array &$settings, $key = 'blazies', array $defaults = []): BlazySettings {
    // @todo @trigger_error('verify is deprecated in blazy:8.x-2.17 and is removed from blazy:3.0.0. Use \Drupal\blazy\BlazyInterface::verifySafely() instead. See https://www.drupal.org/node/3367291', E_USER_DEPRECATED);
    return Internals::verify($settings, $key, $defaults);
  }

  /**
   * Deprecated in blazy:8.x-2.17, added in blazy:8.x-2.17.
   *
   * @todo deprecated in blazy:8.x-2.17 and is removed from blazy:3.0.0. Use
   * \Drupal\blazy\BlazyInterface::hashtag() instead.
   * @see https://www.drupal.org/node/3367291
   */
  public static function hashtag(array &$data, $key = 'settings', $unset = FALSE): void {
    // @todo @trigger_error('hashtag is deprecated in blazy:8.x-2.17 and is removed from blazy:3.0.0. Use \Drupal\blazy\BlazyInterface::hashtag() instead. See https://www.drupal.org/node/3367291', E_USER_DEPRECATED);
    Internals::hashtag($data, $key, $unset);
  }

  /**
   * Deprecated in blazy:8.x-2.17, added in blazy:8.x-2.17.
   *
   * @todo deprecated in blazy:8.x-2.17 and is removed from blazy:3.0.0. Use
   * \Drupal\blazy\BlazyInterface::toHashtag() instead.
   * @see https://www.drupal.org/node/3367291
   */
  public static function toHashtag(array $data, $key = 'settings', $default = []) {
    // @todo @trigger_error('toHashtag is deprecated in blazy:8.x-2.17 and is removed from blazy:3.0.0. Use \Drupal\blazy\BlazyInterface::toHashtag() instead. See https://www.drupal.org/node/3367291', E_USER_DEPRECATED);
    return Internals::toHashtag($data, $key, $default);
  }

  /**
   * Deprecated in blazy:8.x-2.17.
   *
   * @todo deprecated in blazy:8.x-2.17 and is removed from blazy:3.0.0. Use
   * none instead.
   * @see https://www.drupal.org/node/3367291
   */
  public static function which(array &$settings, $lazy, $class, $attribute): void {
    // Do nothing.
  }

}
