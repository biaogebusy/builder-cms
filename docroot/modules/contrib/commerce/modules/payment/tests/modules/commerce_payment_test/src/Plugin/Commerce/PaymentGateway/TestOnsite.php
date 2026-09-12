<?php

namespace Drupal\commerce_payment_test\Plugin\Commerce\PaymentGateway;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\commerce_payment\Attribute\CommercePaymentGateway;
use Drupal\commerce_payment_example\Plugin\Commerce\PaymentGateway\Onsite;
use Drupal\commerce_payment_example\PluginForm\Onsite\PaymentMethodAddForm;

/**
 * Provides the Test on-site payment gateway.
 *
 * This is a copy of example_onsite with a different display_label.
 */
#[CommercePaymentGateway(
  id: "test_onsite",
  label: new TranslatableMarkup("Test (On-site)"),
  display_label: new TranslatableMarkup("Test"),
  forms: [
    "add-payment-method" => PaymentMethodAddForm::class,
  ],
  payment_method_types: ["credit_card"],
  credit_card_types: [
    "amex", "dinersclub", "discover", "jcb", "maestro", "mastercard", "visa",
  ],
)]
class TestOnsite extends Onsite {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return parent::defaultConfiguration() + [
      'skip_add_payment_method_form' => FALSE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function hasFormClass($operation) {
    if (!empty($this->configuration['skip_add_payment_method_form']) &&
      $operation === 'checkout-add-payment-method') {
      return FALSE;
    }

    return parent::hasFormClass($operation);
  }

}
