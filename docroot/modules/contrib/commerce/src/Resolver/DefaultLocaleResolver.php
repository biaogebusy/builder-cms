<?php

namespace Drupal\commerce\Resolver;

use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\commerce\CurrentCountryInterface;
use Drupal\commerce\Locale;

/**
 * Returns the locale based on the current language and country.
 */
class DefaultLocaleResolver implements LocaleResolverInterface {

  /**
   * Constructs a new DefaultLocaleResolver object.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\commerce\CurrentCountryInterface $currentCountry
   *   The current country.
   */
  public function __construct(protected LanguageManagerInterface $languageManager, protected CurrentCountryInterface $currentCountry) {}

  /**
   * {@inheritdoc}
   */
  public function resolve() {
    // The getCurrentLanguage() fallback is a workaround for core bug #2684873.
    $language = $this->languageManager->getConfigOverrideLanguage() ?: $this->languageManager->getCurrentLanguage();
    $langcode = $language->getId();
    $langcode_parts = explode('-', $langcode);
    if (count($langcode_parts) > 1 && strlen(end($langcode_parts)) == 2) {
      // The current language already has a country component (e.g. 'pt-br'),
      // it qualifies as a full locale.
      $locale = $langcode;
    }
    elseif ($country = $this->currentCountry->getCountry()) {
      // Assemble the locale using the resolved country. This can result
      // in non-existent combinations such as 'en-RS', it's up to the locale
      // consumers (e.g. the number format repository) to perform fallback.
      $locale = $langcode . '-' . $country;
    }
    else {
      // Worst case scenario, the country is unknown.
      $locale = $langcode;
    }

    return new Locale($locale);
  }

}
