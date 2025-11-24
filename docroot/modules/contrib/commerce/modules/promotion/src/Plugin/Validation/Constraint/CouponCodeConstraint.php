<?php

namespace Drupal\commerce_promotion\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Drupal\Core\Validation\Plugin\Validation\Constraint\UniqueFieldConstraint;

/**
 * Ensures coupon code uniqueness.
 */
#[Constraint(
  id: "CouponCode",
  label: new TranslatableMarkup("Coupon code", [], ["context" => "Validation"]),
)]
class CouponCodeConstraint extends UniqueFieldConstraint {

  public $message = 'Coupon code matching is case-insensitive, and codes must be unique. %value cannot be used because it matches an existing code.';

}
