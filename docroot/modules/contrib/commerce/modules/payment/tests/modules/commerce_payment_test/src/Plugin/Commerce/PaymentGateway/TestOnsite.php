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
class TestOnsite extends Onsite {}
