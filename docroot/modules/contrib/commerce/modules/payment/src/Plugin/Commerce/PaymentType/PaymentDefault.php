<?php

namespace Drupal\commerce_payment\Plugin\Commerce\PaymentType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\commerce_payment\Attribute\CommercePaymentType;

/**
 * Provides the default payment type.
 */
#[CommercePaymentType(
  id: "payment_default",
  label: new TranslatableMarkup("Default"),
)]
class PaymentDefault extends PaymentTypeBase {

  /**
   * {@inheritdoc}
   */
  public function buildFieldDefinitions() {
    return [];
  }

}
