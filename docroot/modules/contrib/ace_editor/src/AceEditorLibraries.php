<?php

declare(strict_types=1);

namespace Drupal\ace_editor;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\Core\Extension\ProfileExtensionList;
use Drupal\Core\File\FileSystemInterface;

/**
 * Locates the external Ace library and builds its Drupal libraries.
 *
 * Holds the logic that used to live in the procedural helpers in
 * ace_editor.module. Those functions are kept as thin backwards compatible
 * wrappers that delegate here.
 */
class AceEditorLibraries {

  /**
   * Statically cached library path for the current request.
   *
   * FALSE means "not found"; NULL means "not resolved yet".
   */
  protected string|false|null $libPath = NULL;

  /**
   * The cache id under which the resolved library path is stored.
   */
  protected const LIB_PATH_CID = 'ace_editor.lib_path';

  public function __construct(
    protected FileSystemInterface $fileSystem,
    protected ModuleExtensionList $moduleExtensionList,
    protected ProfileExtensionList $profileExtensionList,
    protected ConfigFactoryInterface $configFactory,
    protected ?string $installProfile,
    protected CacheBackendInterface $cacheDiscovery,
  ) {
  }

  /**
   * Finds the Ace library path relative to the Drupal root.
   *
   * The library is searched in the directory DRUPAL_ROOT/libraries.
   * DRUPAL_ROOT/libraries is recommended for storing external libraries.
   * For preserving backwards compatibility, ace_editor/libraries is also
   * checked.
   *
   * @return string|false
   *   The path of the directory holding ace.js, with a leading and trailing
   *   slash, or FALSE when the library is not installed.
   */
  public function libPath(): string|false {
    if ($this->libPath !== NULL) {
      return $this->libPath;
    }

    // Locating the library walks the candidate directories recursively, and
    // the path is now read on every page that carries an Ace library, so keep
    // the result until the next cache rebuild (issue #3326303).
    if ($cached = $this->cacheDiscovery->get(static::LIB_PATH_CID)) {
      $this->libPath = $cached->data;
      return $this->libPath;
    }

    $paths_to_check = [
      '/libraries/ace',
      '/libraries/ace-builds',
      '/' . $this->moduleExtensionList->getPath('ace_editor') . '/libraries',
    ];

    // Add profile path only if a profile is configured.
    if ($this->installProfile) {
      $paths_to_check[] = '/' . $this->profileExtensionList->getPath($this->installProfile) . '/libraries/ace';
    }

    $this->libPath = FALSE;
    foreach ($paths_to_check as $path) {
      if (!is_dir(DRUPAL_ROOT . $path)) {
        continue;
      }

      $found = $this->fileSystem->scanDirectory(DRUPAL_ROOT . $path, '/^ace\.js$/', ['recurse' => TRUE]);
      if ($found) {
        // The ace-builds package ships four builds of the same library;
        // prefer the minified no-conflict one (issue #3322712).
        $uris = array_map(static fn(object $file): string => $file->uri, array_values($found));
        $preferred = AceEditorLibraryPreference::preferred($uris);
        // Anchor the pattern: a parent directory named after ace.js would
        // otherwise be mangled.
        $this->libPath = substr(preg_replace('/ace\.js$/', '', $preferred), strlen(DRUPAL_ROOT));
        break;
      }
    }
    $this->cacheDiscovery->set(static::LIB_PATH_CID, $this->libPath);

    return $this->libPath;
  }

  /**
   * Returns the URL of the library directory, for the Ace JavaScript.
   *
   * Ace resolves its dynamically loaded modes, themes and workers against the
   * URL of its own script. Publishing the directory lets the JavaScript set
   * that path explicitly, so it stays correct however the asset is delivered
   * (issue #3326303).
   *
   * @return string|null
   *   The library URL, including the site base path, or NULL when the library
   *   is not installed.
   */
  public function libUrl(): ?string {
    $path = $this->libPath();
    if (!$path) {
      return NULL;
    }

    // The base path carries a site served from a subdirectory.
    return base_path() . ltrim($path, '/');
  }

  /**
   * Builds the dynamic theme and mode libraries from the Ace library.
   *
   * @return array[]
   *   Library definitions keyed by "<theme|mode>.<name>".
   */
  public function buildLibraryInfo(): array {
    $path = $this->libPath();
    if (!$path) {
      return [];
    }

    // Collects all theme and mode files available.
    $files = $this->fileSystem->scanDirectory(DRUPAL_ROOT . $path, '/(theme|mode)-(.+)\.js$/', ['recurse' => FALSE]);

    $libraries = [];
    foreach ($files as $file_info) {
      $asset = explode('-', $file_info->name);
      $library_name = $asset[0] . '.' . $asset[1];
      $libraries[$library_name] = $path . $file_info->filename;
    }

    $libs = [];
    foreach ($libraries as $key => $value) {
      $libs[$key] = [
        'js' => [
          $value => [],
        ],
      ];
    }
    return $libs;
  }

  /**
   * Injects the located Ace assets into the module's static libraries.
   *
   * @param array $libraries
   *   The module's library definitions, altered in place.
   */
  public function alterLibraryInfo(array &$libraries): void {
    $library_path = $this->libPath();
    if (!$library_path) {
      return;
    }
    // The Ace builds are already minified and must not be aggregated: Ace
    // derives the directory of its dynamically loaded modes, themes and
    // workers from its own script URL, which an aggregate breaks
    // (issue #3322712).
    $ace_asset = ['weight' => -2, 'minified' => TRUE, 'preprocess' => FALSE];
    $libraries['primary']['js'][$library_path . 'ace.js'] = $ace_asset;
    // The search box extension powers the Find (Ctrl-F) and Replace (Ctrl-H)
    // shortcuts, which do nothing without it (issue #3473555). Only the
    // editing library needs it: the formatter and the filter render read-only
    // code, so loading it there would be payload nobody uses.
    $libraries['primary']['js'][$library_path . 'ext-searchbox.js'] = $ace_asset;
    $config = $this->configFactory->get('ace_editor.settings')->get();
    // The key always exists in the shipped configuration, so isset() loaded
    // the extension even with autocomplete turned off.
    if (!empty($config['auto_complete'])) {
      $libraries['primary']['js'][$library_path . 'ext-language_tools.js'] = $ace_asset;
    }
    $libraries['formatter']['js'][$library_path . 'ace.js'] = $ace_asset;
    $libraries['filter']['js'][$library_path . 'ace.js'] = $ace_asset;
    $libraries['widget']['js'][$library_path . 'ace.js'] = $ace_asset;
    // The field widget is an editing surface, so it needs the same extensions
    // the text editor loads.
    $libraries['widget']['js'][$library_path . 'ext-searchbox.js'] = $ace_asset;
    if (!empty($config['auto_complete'])) {
      $libraries['widget']['js'][$library_path . 'ext-language_tools.js'] = $ace_asset;
    }
  }

}
