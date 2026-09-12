<?php

declare(strict_types=1);

namespace Drupal\ace_editor;

/**
 * Chooses the best Ace build among the candidates found on disk.
 *
 * The ace-builds package ships the same library four times: a plain source
 * build, a minified build, and "no conflict" variants of both. The no-conflict
 * builds do not define the global AMD "define" and "require" functions, so
 * they cannot clash with another AMD loader on the page, and the minified
 * builds transfer far less. Preference order is therefore: minified and
 * no-conflict, then no-conflict, then minified, then the plain source.
 */
final class AceEditorLibraryPreference {

  /**
   * The build directories, most preferred first.
   */
  protected const PREFERENCE = [
    '/src-min-noconflict/',
    '/src-noconflict/',
    '/src-min/',
    '/src/',
  ];

  /**
   * Returns the most preferred candidate.
   *
   * @param string[] $uris
   *   The ace.js paths found on disk.
   *
   * @return string|null
   *   The preferred path, or NULL when the list is empty. A candidate that
   *   matches no known build directory ranks last, so a custom layout still
   *   works.
   */
  public static function preferred(array $uris): ?string {
    $uris = array_values(array_filter($uris));
    if (!$uris) {
      return NULL;
    }

    $best = $uris[0];
    $best_rank = PHP_INT_MAX;
    foreach ($uris as $uri) {
      foreach (self::PREFERENCE as $rank => $directory) {
        if (str_contains($uri, $directory) && $rank < $best_rank) {
          $best = $uri;
          $best_rank = $rank;
          break;
        }
      }
    }
    return $best;
  }

}
