<?php

namespace Drupal\commerce_price\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Currency constraint.
 */
#[Constraint(
  id: "Currency",
  label: new TranslatableMarkup("Currency", [], ["context" => "Validation"]),
  type: "commerce_price",
)]
class CurrencyConstraint extends SymfonyConstraint {

  public $availableCurrencies = [];
  public $invalidMessage = 'The currency %value is not valid.';
  public $notAvailableMessage = 'The currency %value is not available.';

}
