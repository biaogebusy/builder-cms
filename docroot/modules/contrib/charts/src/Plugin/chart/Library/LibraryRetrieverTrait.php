<?php

namespace Drupal\charts\Plugin\chart\Library;

/**
 * Implement a method to retrieve the "site_default" library.
 */
trait LibraryRetrieverTrait {

  /**
   * Get the library.
   *
   * @param string $library
   *   The library.
   * @param array $definitions
   *   The library definitions.
   *
   * @return string
   *   The library.
   */
  private function getLibrary(string $library, array $definitions = []): string {
    if (!$definitions && isset($this->chartManager)) {
      // @phpstan-ignore property.notFound (because we check for the property)
      $definitions = $this->chartsManager->getDefinitions();
    }

    if (!$definitions) {
      // This shouldn't happen, but if it happens, let's return an empty
      // string.
      return $library;
    }

    // If the library is missing, use the one set in the Chart configuration
    // page.
    if (!$library || $library === 'site_default') {
      $charts_settings = $this->configFactory->get('charts.settings');
      $default_settings_library = $charts_settings->get('charts_default_settings.library');
      return !empty($default_settings_library) ? $default_settings_library : key($definitions);
    }

    if (!isset($definitions[$library])) {
      return key($definitions);
    }

    return $library;
  }

}
