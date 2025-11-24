<?php

namespace Drupal\commerce_payment\Plugin\Commerce\PaymentMethodType;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\commerce_payment\Attribute\CommercePaymentMethodType;
use Drupal\commerce_payment\CreditCard as CreditCardHelper;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\FailedPaymentDetailsInterface;
use Drupal\entity\BundleFieldDefinition;

/**
 * Provides the credit card payment method type.
 */
#[CommercePaymentMethodType(
  id: "credit_card",
  label: new TranslatableMarkup('Credit card'),
)]
class CreditCard extends PaymentMethodTypeBase implements FailedPaymentDetailsInterface {

  /**
   * {@inheritdoc}
   */
  public function buildLabel(PaymentMethodInterface $payment_method) {
    $card_type = $payment_method->card_type->value;
    if ($card_type !== NULL) {
      $card_type = CreditCardHelper::getType($payment_method->card_type->value)->getLabel();
    }
    else {
      $card_type = $this->t('Credit card');
    }
    $card_number = $payment_method->card_number->value ?? '';
    $args = [
      '@card_type' => $card_type,
      '@card_number' => $card_number,
    ];
    return $card_number ? $this->t('@card_type ending in @card_number', $args) : $this->t('@card_type', $args);
  }

  /**
   * {@inheritdoc}
   */
  public function failedPaymentDetails(PaymentMethodInterface $payment_method): array {
    return ['card_type' => $payment_method->card_type->value];
  }

  /**
   * {@inheritdoc}
   */
  public function buildFieldDefinitions() {
    $fields = parent::buildFieldDefinitions();

    $fields['card_type'] = BundleFieldDefinition::create('list_string')
      ->setLabel($this->t('Card type'))
      ->setDescription($this->t('The credit card type.'))
      ->setRequired(TRUE)
      ->setSetting('allowed_values_function', ['\Drupal\commerce_payment\CreditCard', 'getTypeLabels']);

    $fields['card_number'] = BundleFieldDefinition::create('string')
      ->setLabel($this->t('Card number'))
      ->setDescription($this->t('The last few digits of the credit card number'))
      ->setRequired(TRUE);

    // card_exp_month and card_exp_year are not required because they might
    // not be known (tokenized non-reusable payment methods).
    $fields['card_exp_month'] = BundleFieldDefinition::create('integer')
      ->setLabel($this->t('Card expiration month'))
      ->setDescription($this->t('The credit card expiration month.'))
      ->setSetting('size', 'tiny');

    $fields['card_exp_year'] = BundleFieldDefinition::create('integer')
      ->setLabel($this->t('Card expiration year'))
      ->setDescription($this->t('The credit card expiration year.'))
      ->setSetting('size', 'small');

    return $fields;
  }

}
