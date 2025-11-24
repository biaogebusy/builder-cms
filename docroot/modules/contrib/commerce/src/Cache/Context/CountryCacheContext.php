<?php

namespace Drupal\commerce\Cache\Context;

use Drupal\commerce\CurrentCountryInterface;
use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\Context\CacheContextInterface;

/**
 * Defines the country cache context, for "per country" caching.
 *
 * Cache context ID: 'country'.
 */
class CountryCacheContext implements CacheContextInterface {

  /**
   * Constructs a new CountryCacheContext object.
   *
   * @param \Drupal\commerce\CurrentCountryInterface $currentCountry
   *   The current country.
   */
  public function __construct(protected CurrentCountryInterface $currentCountry) {}

  /**
   * {@inheritdoc}
   */
  public static function getLabel() {
    return t('Country');
  }

  /**
   * {@inheritdoc}
   */
  public function getContext() {
    // In case the current country cannot be determined, return "none" as the
    // cache context.
    return $this->currentCountry->getCountry()?->getCountryCode() ?? 'none';
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata() {
    return new CacheableMetadata();
  }

}
