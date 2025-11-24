<?php

namespace Drupal\commerce_payment\Plugin\Commerce\PaymentMethodType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\commerce_payment\Attribute\CommercePaymentMethodType;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\entity\BundleFieldDefinition;

/**
 * Provides the PayPal payment method type.
 */
#[CommercePaymentMethodType(
  id: "paypal",
  label: new TranslatableMarkup('PayPal'),
)]
class PayPal extends PaymentMethodTypeBase {

  /**
   * {@inheritdoc}
   */
  public function buildLabel(PaymentMethodInterface $payment_method) {
    $args = [
      '@paypal_mail' => $payment_method->paypal_mail->value,
    ];
    return $this->t('PayPal (@paypal_mail)', $args);
  }

  /**
   * {@inheritdoc}
   */
  public function buildFieldDefinitions() {
    $fields = parent::buildFieldDefinitions();

    $fields['paypal_mail'] = BundleFieldDefinition::create('email')
      ->setLabel(t('PayPal Email'))
      ->setDescription(t('The email address associated with the PayPal account.'))
      ->setRequired(TRUE);

    return $fields;
  }

}
