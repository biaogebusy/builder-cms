<?php

namespace Drupal\social_api\Utility;

use Composer\InstalledVersions;

/**
 * Provides utilities for implementer installation.
 */
class SocialApiImplementerInstaller {

  /**
   * Checks the library required by an implementer.
   *
   * @param string $machine_name
   *   The module machine name.
   * @param string $name
   *   The module name.
   * @param string $library
   *   The library machine name.
   * @param float $min_version
   *   The min version required.
   * @param float $max_version
   *   The max version required.
   *
   * @return array
   *   Requirements messages.
   */
  public static function checkLibrary(string $machine_name, string $name, string $library, float $min_version, float $max_version): array {
    $requirements = [];

    // Ensure library is installed.
    try {
      $version = InstalledVersions::getVersion($library);

      // Ensure library version meets constraints.
      if ($version < $min_version || $version > $max_version) {
        $requirements[$machine_name] = [
          'description' => t(
            "@name could not be installed because an incompatible version of @library was detected. Please read the installation instructions.",
            ['@name' => $name, '@library' => $library]
          ),
          'severity' => REQUIREMENT_ERROR,
        ];
      }
    }
    catch (\OutOfBoundsException) {
      $requirements[$machine_name] = [
        'description' => t(
          "@name could not be installed because @library was not found. @name must be installed using Composer. Please read the installation instructions.",
          ['@name' => $name, '@library' => $library]
        ),
        'severity' => REQUIREMENT_ERROR,
      ];
    }

    return $requirements;
  }

}
