<?php

namespace Drupal\blazy\Asset;

use Drupal\Core\Asset\LibraryDiscovery;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Routing\RouteMatchInterface;

/**
 * Provides libraries utilities.
 */
interface LibrariesInterface {

  /**
   * Returns the cache service.
   *
   * @return \Drupal\Core\Cache\CacheBackendInterface
   *   The app root.
   */
  public function cache(): CacheBackendInterface;

  /**
   * Retrieves the config factory service.
   *
   * @return \Drupal\Core\Config\ConfigFactoryInterface
   *   The config factory.
   */
  public function configFactory(): ConfigFactoryInterface;

  /**
   * Retrieves the library descovery service.
   *
   * @return \Drupal\Core\Asset\LibraryDiscovery
   *   The library discovery.
   */
  public function discovery(): LibraryDiscovery;

  /**
   * Retrieves the module handler service.
   *
   * @return \Drupal\Core\Extension\ModuleHandlerInterface
   *   The module handler.
   */
  public function moduleHandler(): ModuleHandlerInterface;

  /**
   * Returns the app root.
   *
   * @return string
   *   The app root.
   */
  public function root(): string;

  /**
   * Retrieves the currently active route match object.
   *
   * @return \Drupal\Core\Routing\RouteMatchInterface
   *   The currently active route match object.
   */
  public function routeMatch(): RouteMatchInterface;

  /**
   * Returns library attachments suitable for #attached property.
   *
   * @param array $attach
   *   The settings which determine what library to attach, empty to defaults.
   *
   * @return array
   *   The supported libraries.
   */
  public function attach(array $attach = []): array;

  /**
   * Gets a single library defined by an extension by name.
   *
   * @param string $extension
   *   The name of the extension that registered a library.
   * @param string $name
   *   The name of a registered library to retrieve.
   *
   * @return array
   *   The definition of the requested library, if $name was passed and it
   *   exists, otherwise empty array.
   */
  public function byName($extension, $name): array;

  /**
   * Returns any config, or keyed by the $setting_name.
   *
   * @param string $key
   *   The setting key.
   * @param string $group
   *   The settings object group key.
   *
   * @return mixed
   *   The config value(s), or empty.
   */
  public function config($key = NULL, $group = 'blazy.settings');

  /**
   * Returns any config by the $group, alternative to ugly NULL key.
   *
   * @param string $group
   *   The settings object group key.
   *
   * @return array
   *   The config values, or empty array.
   */
  public function configMultiple($group = 'blazy.settings'): array;

  /**
   * Returns cached options identified by its cache ID, normally alterable data.
   *
   * @param string $cid
   *   The cache ID, als used for the hook_alter.
   * @param array $data
   *   The given data to cache, accepting empty array to trigger hook_alter.
   * @param bool $as_options
   *   Whether to use it for select options.
   * @param array $info
   *   The optional info containing:
   *   - reset: Whether to bypass cache,
   *   - alter: key for the hook_alter, otherwise $cid.
   *   - context: additional data or contextual info for the hook_alter.
   *
   * @return array
   *   The cache data/ options.
   */
  public function getCachedData(
    $cid,
    array $data = [],
    $as_options = TRUE,
    array $info = []
  ): array;

  /**
   * Return the cache metadata common for all blazy-related modules.
   *
   * @param array $build
   *   The build containing #settings which has cache definitions.
   *
   * @return array
   *   The cache metadata suitable for #cache property.
   */
  public function getCacheMetadata(array $build): array;

  /**
   * Returns drupalSettings for IO.
   *
   * @param array $attach
   *   The settings which determine what library to attach, empty for defaults.
   *
   * @return object
   *   The supported IO drupalSettings.
   */
  public function getIoSettings(array $attach = []): object;

  /**
   * Retrieves the libraries.
   *
   * @param array $names
   *   The library names, e.g.: ['colorbox', 'slick', 'dompurify'].
   * @param bool $base_path
   *   Whether to prefix it with an a base path.
   *
   * @return array
   *   The found libraries keyed by its name, or empty array.
   */
  public function getLibraries(array $names, $base_path = FALSE): array;

  /**
   * Return the available lightboxes, to be cached to avoid disk lookups.
   */
  public function getLightboxes(): array;

  /**
   * Retrieves a library path.
   *
   * A few libraries have inconsistent namings, given different packagers:
   *   - splide x splidejs--splide
   *   - slick x slick-carousel
   *   - DOMPurify x dompurify, etc.
   *
   * @param array|string $name
   *   The library name(s), e.g.: 'colorbox', or ['DOMPurify', 'dompurify'].
   * @param bool $base_path
   *   Whether to prefix it with a base path.
   *
   * @return string|null
   *   The first found path to the library, or NULL if not found.
   */
  public function getPath($name, $base_path = FALSE): ?string;

  /**
   * Returns escaped options.
   *
   * @param array $options
   *   The given options.
   *
   * @return array
   *   The modified array of options suitable for select options.
   */
  public function toOptions(array $options): array;

}
