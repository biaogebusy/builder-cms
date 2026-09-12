<?php

namespace Drupal\acquia_connector\Traits;

trait UtilityTrait {

  /**
   * Get environment variables data.
   *
   * @param array $variables
   *   Array of environment variable keys to fetch.
   *
   * @return array
   *   An associative array of environment variable values.
   */
  protected function getEnvironmentInformation(array $variables): array {
    $metadata = [];
    foreach ($variables as $var) {
      if (!empty(getenv($var))) {
        $metadata[$var] = getenv($var);
      }
    }
    return $metadata;
  }

}
