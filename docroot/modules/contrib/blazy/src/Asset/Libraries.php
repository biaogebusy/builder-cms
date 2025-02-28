<?php

namespace Drupal\blazy\Asset;

use Drupal\blazy\BlazyDefault;
use Drupal\blazy\internals\Internals;
use Drupal\blazy\Media\Preloader;
use Drupal\blazy\Theme\Lightbox;
use Drupal\Core\Asset\LibrariesDirectoryFileFinder;
use Drupal\Core\Asset\LibraryDiscovery;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides libraries utilities.
 *
 * @todo enable the service at 3.x, non-functional till a minimum D9.3.
 */
class Libraries implements LibrariesInterface {

  /**
   * The app root.
   *
   * @var string
   */
  protected $root;

  /**
   * The cache backend.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The library discovery service.
   *
   * @var \Drupal\Core\Asset\LibraryDiscovery
   */
  protected $discovery;

  /**
   * The library finder service.
   *
   * @var \Drupal\Core\Asset\LibrariesDirectoryFileFinder
   */
  protected $finder;

  /**
   * The module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The route match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * The cached data/ options.
   *
   * @var array
   */
  protected $cachedData;

  /**
   * Constructs a Libraries manager object.
   */
  public function __construct(
    $root,
    CacheBackendInterface $cache,
    ConfigFactoryInterface $config_factory,
    LibraryDiscovery $discovery,
    LibrariesDirectoryFileFinder $finder,
    ModuleHandlerInterface $module_handler,
    RouteMatchInterface $route_match
  ) {
    $this->root          = $root;
    $this->cache         = $cache;
    $this->configFactory = $config_factory;
    $this->discovery     = $discovery;
    $this->finder        = $finder;
    $this->moduleHandler = $module_handler;
    $this->routeMatch    = $route_match;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      Internals::root($container),
      $container->get('cache.default'),
      $container->get('config.factory'),
      $container->get('library.discovery'),
      $container->get('library.libraries_directory_file_finder'),
      $container->get('module_handler'),
      $container->get('current_route_match')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function cache(): CacheBackendInterface {
    return $this->cache;
  }

  /**
   * {@inheritdoc}
   */
  public function configFactory(): ConfigFactoryInterface {
    return $this->configFactory;
  }

  /**
   * {@inheritdoc}
   */
  public function discovery(): LibraryDiscovery {
    return $this->discovery;
  }

  /**
   * {@inheritdoc}
   */
  public function moduleHandler(): ModuleHandlerInterface {
    return $this->moduleHandler;
  }

  /**
   * {@inheritdoc}
   */
  public function root(): string {
    return $this->root;
  }

  /**
   * {@inheritdoc}
   */
  public function routeMatch(): RouteMatchInterface {
    return $this->routeMatch;
  }

  /**
   * {@inheritdoc}
   */
  public function attach(array $attach = []): array {
    Internals::postSettings($attach);

    $load    = [];
    $blazies = $attach['blazies'];
    $unblazy = $blazies->is('unblazy', FALSE);
    $unload  = $blazies->ui('nojs.lazy', FALSE) || $blazies->is('unlazy');

    if ($blazies->is('lightbox')) {
      Lightbox::attach($load, $attach, $blazies);
    }

    // Always keep Drupal UI config to support dynamic compat features.
    $config = $this->config('blazy');
    $config['loader'] = !$unload;
    $config['unblazy'] = $unblazy;
    $config['visibleClass'] = $blazies->ui('visible_class') ?: FALSE;

    // One is enough due to various formatters negating each others.
    $compat = $blazies->get('libs.compat');

    // Only if `No JavaScript` option is disabled, or has compat.
    // Compat is a loader for Blur, BG, Video which Native doesn't support.
    if ($compat || !$unload) {
      if ($compat) {
        $config['compat'] = $compat;
      }

      // Modern sites may want to forget oldies, respect.
      if (!$unblazy) {
        $load['library'][] = 'blazy/blazy';
      }

      foreach (BlazyDefault::nojs() as $key) {
        if (empty($blazies->ui('nojs.' . $key))) {
          $lib = $key == 'lazy' ? 'load' : $key;
          $load['library'][] = 'blazy/' . $lib;
        }
      }
    }

    if ($libs = array_filter($blazies->get('libs', []))) {
      foreach (array_keys($libs) as $lib) {
        $key = str_replace('__', '.', $lib);
        $load['library'][] = 'blazy/' . $key;
      }
    }

    // @todo remove for the above once all components are set to libs.
    foreach (BlazyDefault::components() as $component) {
      $key = str_replace('.', '__', $component);
      if ($blazies->get('libs.' . $key, FALSE)) {
        $load['library'][] = 'blazy/' . $component;
      }
    }

    // Adds AJAX helper to revalidate Blazy/ IO, if using VIS, or alike.
    // @todo remove when VIS detaches behaviors properly like IO.
    if ($blazies->use('ajax', FALSE)) {
      $load['library'][] = 'blazy/bio.ajax';
    }

    // Preload.
    if (!empty($attach['preload'])) {
      Preloader::preload($load, $attach);
    }

    // No blazy libraries are loaded when `No JavaScript`, etc. enabled.
    // And the drupalSettings should not be, either. So quiet here.
    if (isset($load['library'])) {
      $load['drupalSettings']['blazy'] = $config;
      $load['drupalSettings']['blazyIo'] = $this->getIoSettings($attach);
      $load['library'] = array_unique($load['library']);
    }
    return $load;
  }

  /**
   * {@inheritdoc}
   */
  public function byName($extension, $name): array {
    return $this->discovery->getLibraryByName($extension, $name) ?: [];
  }

  /**
   * {@inheritdoc}
   */
  public function config($key = NULL, $group = 'blazy.settings') {
    $config  = $this->configFactory->get($group);
    $configs = $config->get();
    unset($configs['_core']);
    return empty($key) ? $configs : $config->get($key);
  }

  /**
   * {@inheritdoc}
   */
  public function configMultiple($group = 'blazy.settings'): array {
    return $this->config(NULL, $group) ?: [];
  }

  /**
   * {@inheritdoc}
   */
  public function getCachedData(
    $cid,
    array $data = [],
    $as_options = TRUE,
    array $info = []
  ): array {
    $reset = $info['reset'] ?? FALSE;
    if (!isset($this->cachedData[$cid]) || $reset) {
      $cache = $this->cache->get($cid);

      if (!$reset && $cache && $data = $cache->data) {
        $this->cachedData[$cid] = $data;
      }
      else {
        $alter   = $info['alter'] ?? $cid;
        $context = $info['context'] ?? [];
        $key     = $info['key'] ?? NULL;

        // Allows empty array to trigger hook_alter.
        if (is_array($data)) {
          $this->moduleHandler->alter($alter, $data, $context);
        }

        // Only if we have data, cache them.
        if ($data && is_array($data)) {
          if (isset($data[1])) {
            $data = array_unique($data, SORT_REGULAR);
          }

          if ($as_options) {
            $data = $this->toOptions($data);
          }
          else {
            ksort($data);
          }

          $count = $key && isset($data[$key]) ? count($data[$key]) : count($data);
          $tags = Cache::buildTags($cid, ['count:' . $count]);
          $this->cache->set($cid, $data, Cache::PERMANENT, $tags);
        }

        $this->cachedData[$cid] = $data;
      }
    }
    return $this->cachedData[$cid] ?: [];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMetadata(array $build): array {
    $settings  = Internals::toHashtag($build) ?: $build;
    $blazies   = Internals::verify($settings);
    $namespace = $blazies->get('namespace', 'blazy');
    $count     = $blazies->total() ?: $blazies->get('count', count($settings));
    $max_age   = $this->config('cache.page.max_age', 'system.performance');
    $max_age   = empty($settings['cache']) ? $max_age : $settings['cache'];
    $id        = Internals::getHtmlId($namespace . $count);
    $id        = $blazies->get('css.id', $id);
    $id        = substr(md5($id), 0, 11);

    // Put them into cxahe.
    $cache             = [];
    $suffixes[]        = $count;
    $cache['tags']     = Cache::buildTags($namespace . ':' . $id, $suffixes, '.');
    $cache['contexts'] = ['languages', 'url.site'];
    $cache['max-age']  = $max_age;
    $cache['keys']     = $blazies->get('cache.metadata.keys', [$id]);

    if ($tags = $blazies->get('cache.metadata.tags', [])) {
      $cache['tags'] = Cache::mergeTags($cache['tags'], $tags);
    }

    return $cache;
  }

  /**
   * {@inheritdoc}
   */
  public function getIoSettings(array $attach = []): object {
    $io = [];
    $thold = $this->config('io.threshold');
    $thold = str_replace(['[', ']'], '', trim($thold ?: '0'));

    // @todo re-check, looks like the default 0 is broken sometimes.
    if ($thold == '0') {
      $thold = '0, 0.25, 0.5, 0.75, 1';
    }

    $thold = strpos($thold, ',') !== FALSE
      ? array_map('trim', explode(',', $thold)) : [$thold];
    $formatted = [];
    foreach ($thold as $value) {
      $formatted[] = strpos($value, '.') !== FALSE ? (float) $value : (int) $value;
    }

    // Respects hook_blazy_attach_alter() for more fine-grained control.
    foreach (['disconnect', 'rootMargin', 'threshold'] as $key) {
      $default = $key == 'rootMargin' ? '0px' : FALSE;
      $value = $key == 'threshold' ? $formatted : $this->config('io.' . $key);
      $io[$key] = $attach['io.' . $key] ?? ($value ?: $default);
    }

    return (object) $io;
  }

  /**
   * {@inheritdoc}
   */
  public function getLibraries(array $names, $base_path = FALSE): array {
    $libraries = [];
    foreach ($this->libraries($names, TRUE) as $key => $path) {
      if ($path) {
        $libraries[$key] = $base_path ? Internals::basePath() . $path : $path;
      }
    }
    return $libraries;
  }

  /**
   * {@inheritdoc}
   */
  public function getLightboxes(): array {
    $lightboxes = ['flybox'];
    if (function_exists('colorbox_theme')) {
      $lightboxes[] = 'colorbox';
    }

    // @todo remove deprecated unmaintained photobox.
    // Most lightboxes are unmantained, only supports mostly used, or robust.
    $paths = [
      'photobox' => 'photobox/photobox/jquery.photobox.js',
      'mfp' => 'magnific-popup/dist/jquery.magnific-popup.min.js',
    ];

    foreach ($paths as $key => $path) {
      if (is_file($this->root . '/libraries/' . $path)) {
        $lightboxes[] = $key;
      }
    }
    return $lightboxes;
  }

  /**
   * {@inheritdoc}
   */
  public function getPath($name, $base_path = FALSE): ?string {
    $library = '';
    $names = is_array($name) ? $name : [$name];
    foreach ($this->libraries($names) as $path) {
      if ($path) {
        $library = $base_path ? Internals::basePath() . $path : $path;
        break;
      }
    }
    return $library;
  }

  /**
   * {@inheritdoc}
   */
  public function toOptions(array $options): array {
    if ($options) {
      $options = array_map('\Drupal\Component\Utility\Html::escape', $options);
      uasort($options, 'strnatcasecmp');
    }
    return $options;
  }

  /**
   * Provides libraries.
   */
  private function libraries(array $libraries, $keyed = FALSE): \Generator {
    foreach ($libraries as $library) {
      $result = $this->finder->find($library);
      if ($keyed) {
        yield $library => $result;
      }
      else {
        yield $result;
      }
    }
  }

}
