<?php

namespace Drupal\commerce_order\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Purchasable entity available reference constraint.
 */
#[Constraint(
  id: "PurchasedEntityAvailable",
  label: new TranslatableMarkup("Purchasable entity available", [], ["context" => "Validation"]),
)]
class PurchasedEntityAvailableConstraint extends SymfonyConstraint {

  /**
   * The default violation message.
   *
   * @var string
   */
  public $message = '%label is not available with a quantity of %quantity.';

  /**
   * {@inheritdoc}
   *
   * This is overridden to make extending the constraint plugin easier. It
   * simplifies the ability to customize the $message property without having
   * to override this method and define the constraint validator.
   */
  public function validatedBy(): string {
    return PurchasedEntityAvailableConstraintValidator::class;
  }

}
