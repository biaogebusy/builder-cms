<?php

namespace Drupal\address\Plugin\Validation\Constraint;

use CommerceGuys\Addressing\Validator\Constraints\AddressFormatConstraint as ExternalAddressFormatConstraint;

/**
 * Address format constraint.
 *
 * @Constraint(
 *   id = "AddressFormat",
 *   label = @Translation("Address Format", context = "Validation"),
 *   type = { "address" }
 * )
 */
class AddressFormatConstraint extends ExternalAddressFormatConstraint {

  /**
   * Whether extended postal code validation is enabled.
   *
   * Extended postal code validation uses subdivision-level patterns to
   * in addition to the country-level pattern supplied by the address format.
   *
   * This feature is deprecated in the parent library, and undesired
   * on the Drupal side.
   *
   * @var bool
   */
  public $extendedPostalCodeValidation = FALSE;

  /**
   * Validation message if a field must be blank.
   *
   * @var string
   */
  public $blankMessage = '@name field must be blank.';

  /**
   * Validation message if a field is required.
   *
   * @var string
   */
  public $notBlankMessage = '@name field is required.';

  /**
   * Validation message if a field has an invalid format.
   *
   * @var string
   */
  public $invalidMessage = '@name field is not in the right format.';

}
