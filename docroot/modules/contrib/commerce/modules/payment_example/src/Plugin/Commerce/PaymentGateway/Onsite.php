<?php

namespace Drupal\commerce_payment_example\Plugin\Commerce\PaymentGateway;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\commerce_payment\Attribute\CommercePaymentGateway;
use Drupal\commerce_payment\CreditCard;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\Exception\HardDeclineException;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\OnsitePaymentGatewayBase;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsZeroBalanceOrderInterface;
use Drupal\commerce_payment\PluginForm\PaymentMethodEditForm;
use Drupal\commerce_payment_example\PluginForm\Onsite\PaymentMethodAddForm;
use Drupal\commerce_price\Price;

/**
 * Provides the On-site payment gateway.
 */
#[CommercePaymentGateway(
  id: "example_onsite",
  label: new TranslatableMarkup("Example (On-site)"),
  display_label: new TranslatableMarkup("Example"),
  forms: [
    "add-payment-method" => PaymentMethodAddForm::class,
    "edit-payment-method" => PaymentMethodEditForm::class,
  ],
  payment_method_types: ["credit_card"],
  credit_card_types: [
    "amex", "dinersclub", "discover", "jcb", "maestro", "mastercard", "visa",
  ],
  requires_billing_information: FALSE,
)]
class Onsite extends OnsitePaymentGatewayBase implements OnsiteInterface, SupportsZeroBalanceOrderInterface {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'api_key' => '',
    ] + parent::defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildConfigurationForm($form, $form_state);

    // Example credential. Also needs matching schema in
    // config/schema/$your_module.schema.yml.
    $form['api_key'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API key'),
      '#default_value' => $this->configuration['api_key'],
      '#required' => TRUE,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    if (!$form_state->getErrors()) {
      $values = $form_state->getValue($form['#parents']);
      $this->configuration['api_key'] = $values['api_key'];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function createPayment(PaymentInterface $payment, $capture = TRUE) {
    $this->assertPaymentState($payment, ['new']);
    $payment_method = $payment->getPaymentMethod();
    $this->assertPaymentMethod($payment_method);

    // Add a built in test for testing decline exceptions.
    // Note: Since requires_billing_information is FALSE, the payment method
    // is not guaranteed to have a billing profile. Confirm tha
    // $payment_method->getBillingProfile() is not NULL before trying to use it.
    if ($billing_profile = $payment_method->getBillingProfile()) {
      /** @var \Drupal\address\Plugin\Field\FieldType\AddressItem $billing_address */
      $billing_address = $billing_profile->get('address')->first();
      if ($billing_address->getPostalCode() == '53140') {
        throw HardDeclineException::createForPayment($payment, 'The payment was declined');
      }
    }

    // Perform the create payment request here, throw an exception if it fails.
    // See \Drupal\commerce_payment\Exception for the available exceptions.
    // Remember to take into account $capture when performing the request.
    $amount = $payment->getAmount();
    $payment_method_token = $payment_method->getRemoteId();
    // The remote ID returned by the request.
    $remote_id = '123456';
    $next_state = $capture ? 'completed' : 'authorization';

    $payment->setState($next_state);
    $payment->setRemoteId($remote_id);
    $payment->setAvsResponseCode('A');
    if (!$payment_method->card_type->isEmpty()) {
      $avs_response_code_label = $this->buildAvsResponseCodeLabel('A', $payment_method->card_type->value);
      $payment->setAvsResponseCodeLabel($avs_response_code_label);
    }
    $payment->save();
  }

  /**
   * {@inheritdoc}
   */
  public function capturePayment(PaymentInterface $payment, ?Price $amount = NULL) {
    $this->assertPaymentState($payment, ['authorization']);
    // If not specified, capture the entire amount.
    $amount = $amount ?: $payment->getAmount();

    // Perform the capture request here, throw an exception if it fails.
    // See \Drupal\commerce_payment\Exception for the available exceptions.
    $remote_id = $payment->getRemoteId();
    $number = $amount->getNumber();

    $payment->setState('completed');
    $payment->setAmount($amount);
    $payment->save();
  }

  /**
   * {@inheritdoc}
   */
  public function voidPayment(PaymentInterface $payment) {
    $this->assertPaymentState($payment, ['authorization']);
    // Perform the void request here, throw an exception if it fails.
    // See \Drupal\commerce_payment\Exception for the available exceptions.
    $remote_id = $payment->getRemoteId();

    $payment->setState('authorization_voided');
    $payment->save();
  }

  /**
   * {@inheritdoc}
   */
  public function refundPayment(PaymentInterface $payment, ?Price $amount = NULL) {
    $this->assertPaymentState($payment, ['completed', 'partially_refunded']);
    // If not specified, refund the entire amount.
    $amount = $amount ?: $payment->getAmount();
    $this->assertRefundAmount($payment, $amount);

    // Perform the refund request here, throw an exception if it fails.
    // See \Drupal\commerce_payment\Exception for the available exceptions.
    $remote_id = $payment->getRemoteId();
    $number = $amount->getNumber();

    $old_refunded_amount = $payment->getRefundedAmount();
    $new_refunded_amount = $old_refunded_amount->add($amount);
    if ($new_refunded_amount->lessThan($payment->getAmount())) {
      $payment->setState('partially_refunded');
    }
    else {
      $payment->setState('refunded');
    }

    $payment->setRefundedAmount($new_refunded_amount);
    $payment->save();
  }

  /**
   * {@inheritdoc}
   */
  public function createPaymentMethod(PaymentMethodInterface $payment_method, array $payment_details) {
    $required_keys = [
      // The expected keys are payment gateway specific and usually match
      // the PaymentMethodAddForm form elements. They are expected to be valid.
      'type', 'number', 'expiration',
    ];
    foreach ($required_keys as $required_key) {
      if (empty($payment_details[$required_key])) {
        throw new \InvalidArgumentException(sprintf('$payment_details must contain the %s key.', $required_key));
      }
    }

    // Add details to payment method before declining so that these details are
    // available to the FailedPaymentEvent.
    $payment_method->card_type = $payment_details['type'];
    // Only the last 4 numbers are safe to store.
    $payment_method->card_number = substr($payment_details['number'], -4);
    $payment_method->card_exp_month = $payment_details['expiration']['month'];
    $payment_method->card_exp_year = $payment_details['expiration']['year'];

    // Add a built in test for testing decline exceptions.
    // Note: Since requires_billing_information is FALSE, the payment method
    // is not guaranteed to have a billing profile. Confirm tha
    // $payment_method->getBillingProfile() is not NULL before trying to use it.
    if ($billing_profile = $payment_method->getBillingProfile()) {
      /** @var \Drupal\address\Plugin\Field\FieldType\AddressItem $billing_address */
      $billing_address = $billing_profile->get('address')->first();
      if ($billing_address->getPostalCode() == '53141') {
        throw HardDeclineException::createForPayment($payment_method, 'The payment method was declined');
      }
    }

    // If the remote API needs a remote customer to be created.
    $owner = $payment_method->getOwner();
    if ($owner && !$owner->isAnonymous()) {
      $customer_id = $this->getRemoteCustomerId($owner);
      // If $customer_id is empty, create the customer remotely and then do
      // $this->setRemoteCustomerId($owner, $customer_id);
      // $owner->save();
    }

    // Perform the create request here, throw an exception if it fails.
    // See \Drupal\commerce_payment\Exception for the available exceptions.
    // You might need to do different API requests based on whether the
    // payment method is reusable: $payment_method->isReusable().
    // Non-reusable payment methods usually have an expiration timestamp.
    $expires = CreditCard::calculateExpirationTimestamp($payment_details['expiration']['month'], $payment_details['expiration']['year']);
    // The remote ID returned by the request.
    $remote_id = '789';

    $payment_method->setRemoteId($remote_id);
    $payment_method->setExpiresTime($expires);
    $payment_method->save();
  }

  /**
   * {@inheritdoc}
   */
  public function deletePaymentMethod(PaymentMethodInterface $payment_method) {
    // Delete the remote record here, throw an exception if it fails.
    // See \Drupal\commerce_payment\Exception for the available exceptions.
    // Delete the local entity.
    $payment_method->delete();
  }

  /**
   * {@inheritdoc}
   */
  public function updatePaymentMethod(PaymentMethodInterface $payment_method) {
    // Note: Since requires_billing_information is FALSE, the payment method
    // is not guaranteed to have a billing profile. Confirm that
    // $payment_method->getBillingProfile() is not NULL before trying to use it.
    //
    // Perform the update request here, throw an exception if it fails.
    // See \Drupal\commerce_payment\Exception for the available exceptions.
  }

  /**
   * {@inheritdoc}
   */
  public function buildAvsResponseCodeLabel($avs_response_code, $card_type) {
    if ($card_type == 'dinersclub' || $card_type == 'jcb') {
      if ($avs_response_code == 'A') {
        return $this->t('Approved.');
      }
      return NULL;
    }
    return parent::buildAvsResponseCodeLabel($avs_response_code, $card_type);
  }

}
