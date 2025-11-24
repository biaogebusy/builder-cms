<?php

namespace Drupal\commerce_payment\Plugin\Commerce\PaymentType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\commerce_payment\Attribute\CommercePaymentType;

/**
 * Provides the manual payment type.
 */
#[CommercePaymentType(
  id: "payment_manual",
  label: new TranslatableMarkup("Manual"),
  workflow: "payment_manual"
)]
class PaymentManual extends PaymentTypeBase {

  /**
   * {@inheritdoc}
   */
  public function buildFieldDefinitions() {
    return [];
  }

}
