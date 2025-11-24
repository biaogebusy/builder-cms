<?php

namespace Drupal\commerce_promotion\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Coupon valid reference constraint.
 *
 * Verifies that coupon is available and applies to the order.
 */
#[Constraint(
  id: "CouponValid",
  label: new TranslatableMarkup("Coupon valid reference", [], ["context" => "Validation"]),
)]
class CouponValidConstraint extends SymfonyConstraint {

  /**
   * The default violation message.
   *
   * @var string
   */
  public $message = 'The provided coupon code is invalid.';

}
