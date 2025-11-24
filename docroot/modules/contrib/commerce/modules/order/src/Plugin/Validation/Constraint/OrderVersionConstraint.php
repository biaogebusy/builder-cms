<?php

namespace Drupal\commerce_order\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Validation constraint for the order version.
 */
#[Constraint(
  id: "OrderVersion",
  label: new TranslatableMarkup("Order version", [], ["context" => "Validation"]),
  type: "entity:commerce_order",
)]
class OrderVersionConstraint extends SymfonyConstraint {

  public $message = 'The order has either been modified by another user, or you have already submitted modifications. As a result, your changes cannot be saved.';

}
