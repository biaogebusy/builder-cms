<?php

namespace Drupal\commerce_product\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Ensures product variation SKU uniqueness.
 */
#[Constraint(
  id: "ProductVariationSku",
  label: new TranslatableMarkup("The SKU of the product variation.", [], ["context" => "Validation"]),
)]
class ProductVariationSkuConstraint extends SymfonyConstraint {

  public $message = 'The SKU %sku is already in use and must be unique.';

}
