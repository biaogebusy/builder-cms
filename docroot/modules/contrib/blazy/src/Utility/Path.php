<?php

namespace Drupal\blazy\Utility;

use Drupal\blazy\Blazy;
use Drupal\blazy\internals\Internals;

/**
 * Provides url, route, request, stream, or any path-related methods.
 *
 * @internal
 *   This is an internal part of the Blazy system and should only be used by
 *   blazy-related code in Blazy module. Please use the public method instead.
 */
class Path {

  /**
   * The AMP page.
   *
   * @var bool|null
   */
  protected static $isAmp;

  /**
   * The preview mode to disable Blazy where JS is not available, or useless.
   *
   * @var bool|null
   */
  protected static $isPreview;

  /**
   * The preview mode to disable interactive elements.
   *
   * @var bool|null
   */
  protected static $isSandboxed;

  /**
   * Retrieves the file url generator service.
   *
   * @return \Drupal\Core\File\FileUrlGenerator|null
   *   The file url generator.
   *
   * @see https://www.drupal.org/node/2940031
   */
  public static function fileUrlGenerator() {
    return Internals::service('file_url_generator');
  }

  /**
   * Retrieves the path resolver.
   *
   * @return \Drupal\Core\Extension\ExtensionPathResolver|null
   *   The path resolver.
   */
  public static function pathResolver() {
    return Internals::service('extension.path.resolver');
  }

  /**
   * Retrieves the request stack.
   *
   * @return \Symfony\Component\HttpFoundation\RequestStack|null
   *   The request stack.
   */
  public static function requestStack() {
    return Internals::service('request_stack');
  }

  /**
   * Retrieves the currently active route match object.
   *
   * @return \Drupal\Core\Routing\RouteMatchInterface|null
   *   The currently active route match object.
   */
  public static function routeMatch() {
    return Internals::service('current_route_match');
  }

  /**
   * Retrieves the stream wrapper manager service.
   *
   * @return \Drupal\Core\StreamWrapper\StreamWrapperManager|null
   *   The stream wrapper manager.
   */
  public static function streamWrapperManager() {
    return Internals::service('stream_wrapper_manager');
  }

  /**
   * Retrieves the request.
   *
   * @return \Symfony\Component\HttpFoundation\Request|null
   *   The request.
   *
   * @see https://github.com/symfony/symfony/blob/6.0/src/Symfony/Component/HttpFoundation/Request.php
   */
  public static function request() {
    if ($stack = self::requestStack()) {
      return $stack->getCurrentRequest();
    }
    return NULL;
  }

  /**
   * Returns the commonly used path, or just the base path.
   *
   * @todo remove drupal_get_path check when min D9.3.
   */
  public static function getPath($type, $name, $absolute = FALSE): ?string {
    if ($resolver = self::pathResolver()) {
      $path = $resolver->getPath($type, $name);
    }
    else {
      $function = 'drupal_get_path';
      /* @phpstan-ignore-next-line */
      $path = is_callable($function) ? $function($type, $name) : '';
    }
    return $absolute ? Internals::basePath() . $path : $path;
  }

  /**
   * Checks if Blazy is in CKEditor preview mode where no JS assets are loaded.
   */
  public static function isPreview(): bool {
    if (!isset(static::$isPreview)) {
      static::$isPreview = self::isAmp() || self::isSandboxed();
    }
    return static::$isPreview;
  }

  /**
   * Checks if Blazy is in AMP pages.
   */
  public static function isAmp(): bool {
    if (!isset(static::$isAmp)) {
      $request = self::request();
      static::$isAmp = $request && $request->query->get('amp') !== NULL;
    }
    return static::$isAmp;
  }

  /**
   * In CKEditor without JS assets, interactive elements must be sandboxed.
   */
  public static function isSandboxed(): bool {
    if (!isset(static::$isSandboxed)) {
      $check = FALSE;
      if ($router = self::routeMatch()) {
        if ($route = $router->getRouteName()) {
          $edits = ['entity_browser.', 'edit_form', 'add_form', '.preview'];
          foreach ($edits as $key) {
            if (Blazy::has($route, $key)) {
              $check = TRUE;
              break;
            }
          }
        }
      }

      static::$isSandboxed = $check;
    }
    return static::$isSandboxed;
  }

  /**
   * Returns multiple libraries keyed by its name.
   *
   * @todo remove for \Drupal\blazy\Asset\Libraries::getLibraries() at 3.x.
   */
  public static function getLibraries(array $names, $base_path = FALSE): array {
    $libraries = [];
    foreach (self::libraries($names, TRUE) as $key => $path) {
      if ($path) {
        $libraries[$key] = $base_path ? Internals::basePath() . $path : $path;
      }
    }
    return $libraries;
  }

  /**
   * Returns the first found library path.
   *
   * @todo remove for \Drupal\blazy\Asset\Libraries::getPath() at 3.x.
   */
  public static function getLibrariesPath($name, $base_path = FALSE): ?string {
    $library = '';
    $names = is_array($name) ? $name : [$name];
    foreach (self::libraries($names) as $path) {
      if ($path) {
        $library = $base_path ? Internals::basePath() . $path : $path;
        break;
      }
    }
    return $library;
  }

  /**
   * Provides a wrapper to replace deprecated libraries_get_path() at ease.
   *
   * @todo remove for \Drupal\blazy\Asset\Libraries methods at 3.x.
   */
  private static function libraries(array $libraries, $keyed = FALSE): \Generator {
    if ($finder = Internals::service('library.libraries_directory_file_finder')) {
      foreach ($libraries as $library) {
        $result = $finder->find($library);
        if ($keyed) {
          yield $library => $result;
        }
        else {
          yield $result;
        }
      }
    }
    else {
      // @todo remove when min D9.2, and make libraries a service at 3.x.
      $dep = 'libraries_get_path';
      foreach ($libraries as $library) {
        // @todo phpstan bug, given different Drupal branches outside tests.
        /* @phpstan-ignore-next-line */
        $result = is_callable($dep) ? $dep($library) : '';
        if ($keyed) {
          yield $library => $result;
        }
        else {
          yield $result;
        }
      }
    }
  }

}
