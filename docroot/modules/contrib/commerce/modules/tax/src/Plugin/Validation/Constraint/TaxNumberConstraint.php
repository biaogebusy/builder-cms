<?php

namespace Drupal\commerce_tax\Plugin\Validation\Constraint;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Validation\Attribute\Constraint;
use Symfony\Component\Validator\Constraint as SymfonyConstraint;

/**
 * Tax number constraint.
 */
#[Constraint(
  id: "TaxNumber",
  label: new TranslatableMarkup("Tax number", [], ["context" => "Validation"]),
  type: "commerce_tax_number",
)]
class TaxNumberConstraint extends SymfonyConstraint {

  public $verify = TRUE;
  public $allowUnverified = FALSE;
  public $invalidMessage = '%name is not in the right format.';
  public $invalidMessageWithExamples = '%name is not in the right format. @examples';
  public $verificationFailedMessage = '%name could not be verified.';

}
