<?php

namespace Drupal\address\Plugin\Validation\Constraint;

use Symfony\Component\Validator\Constraint;

/**
 * Country constraint.
 *
 * @Constraint(
 *   id = "Country",
 *   label = @Translation("Country", context = "Validation"),
 * )
 */
class CountryConstraint extends Constraint {

  /**
   * List of available countries.
   *
   * @var string[]
   */
  public array $availableCountries = [];

  /**
   * Validation message if a country is invalid.
   *
   * @var string
   */
  public string $invalidMessage = 'The country %value is not valid.';

  /**
   * Validation message if a country is not available.
   *
   * @var string
   */
  public string $notAvailableMessage = 'The country %value is not available.';

}
