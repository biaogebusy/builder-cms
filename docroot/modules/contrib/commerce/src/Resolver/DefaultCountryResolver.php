<?php

namespace Drupal\commerce\Resolver;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\commerce\Country;

/**
 * Returns the site's default country.
 */
class DefaultCountryResolver implements CountryResolverInterface {

  /**
   * Constructs a new DefaultCountryResolver object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(protected ConfigFactoryInterface $configFactory) {}

  /**
   * {@inheritdoc}
   */
  public function resolve() {
    $country_code = $this->configFactory->get('system.date')->get('country.default');
    if ($country_code && is_string($country_code)) {
      return new Country($country_code);
    }
  }

}
