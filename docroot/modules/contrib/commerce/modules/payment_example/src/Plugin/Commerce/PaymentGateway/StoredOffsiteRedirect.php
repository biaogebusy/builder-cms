<?php

namespace Drupal\commerce_payment_example\Plugin\Commerce\PaymentGateway;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Attribute\CommercePaymentGateway;
use Drupal\commerce_payment\CreditCard;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Entity\PaymentMethodInterface;
use Drupal\commerce_payment\PaymentMethodStorageInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsStoredPaymentMethodsInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\SupportsZeroBalanceOrderInterface;
use Drupal\commerce_payment_example\PluginForm\OffsiteRedirect\PaymentOffsiteForm;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides an example offsite payment gateway with stored payment methods.
 */
#[CommercePaymentGateway(
  id: "example_stored_offsite_redirect",
  label: new TranslatableMarkup("Example (Off-site redirect with stored payment methods)"),
  display_label: new TranslatableMarkup("Example Stored Offsite"),
  forms: [
    "offsite-payment" => PaymentOffsiteForm::class,
  ],
  payment_method_types: ["credit_card"],
  credit_card_types: [
    "amex", "dinersclub", "discover", "jcb", "maestro", "mastercard", "visa",
  ],
)]
class StoredOffsiteRedirect extends OffsiteRedirect implements SupportsStoredPaymentMethodsInterface, SupportsZeroBalanceOrderInterface {

  /**
   * The messenger.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->messenger = $container->get('messenger');
    return $instance;
  }

  /**
   * {@inheritdoc}
   */
  public function createPaymentMethod(PaymentMethodInterface $payment_method, Request $request) {
    // Off-site gateways often can created stored customer profiles or payment
    // references from a processed transaction. A payment gateway would perform
    // an API action to generate the payment method reference on the gateway
    // here and then save it as a payment method.
    $payment_method->card_type = 'visa';
    $payment_method->card_number = '1111';
    $payment_method->card_exp_month = '12';
    $payment_method->card_exp_year = '26';
    $expires = CreditCard::calculateExpirationTimestamp($payment_method->card_exp_month->value, $payment_method->card_exp_year->value);
    $payment_method->setExpiresTime($expires);
    $payment_method->setRemoteId('1234');
    $payment_method->save();
  }

  /**
   * {@inheritdoc}
   */
  public function deletePaymentMethod(PaymentMethodInterface $payment_method) {
    $payment_method->delete();
  }

  /**
   * {@inheritdoc}
   */
  public function createPayment(PaymentInterface $payment, $capture = TRUE) {
    // If order is free only create the payment method but not the payment.
    if ($payment->getAmount()->isZero()) {
      return;
    }

    $this->assertPaymentState($payment, ['new']);
    $payment_method = $payment->getPaymentMethod();
    $this->assertPaymentMethod($payment_method);

    // Perform the create payment request here, throw an exception if it fails.
    // See \Drupal\commerce_payment\Exception for the available exceptions.
    // Remember to take into account $capture when performing the request.
    $payment_method_token = $payment_method->getRemoteId();
    // The remote ID returned by the request.
    $remote_id = '123456';
    $next_state = $capture ? 'completed' : 'authorization';

    $payment->setState($next_state);
    $payment->setRemoteId($remote_id);
    $payment->save();
  }

  /**
   * {@inheritdoc}
   */
  public function onReturn(OrderInterface $order, Request $request) {
    // This off-site payment gateway example creates a payment method as a
    // part of processing the payment data returned from the gateway.
    $payment_method_storage = $this->entityTypeManager->getStorage('commerce_payment_method');
    assert($payment_method_storage instanceof PaymentMethodStorageInterface);
    $payment_method = $payment_method_storage->createForCustomer(
      'credit_card',
      $this->parentEntity->id(),
      $order->getCustomerId(),
      $order->getBillingProfile()
    );
    // The payment method is created first so that it can be attached to the
    // generated payment transaction.
    $this->createPaymentMethod($payment_method, $request);

    // If order is free only create the payment method but not the payment.
    if ($order->getBalance()->isZero()) {
      return;
    }

    $payment_storage = $this->entityTypeManager->getStorage('commerce_payment');
    $payment = $payment_storage->create([
      'state' => 'completed',
      'amount' => $order->getBalance(),
      'payment_gateway' => $this->parentEntity->id(),
      'order_id' => $order->id(),
      'remote_id' => $request->query->get('txn_id'),
      'remote_state' => $request->query->get('payment_status'),
      'payment_method' => $payment_method,
      'avs_response_code' => 'Z',
    ]);
    if (!$payment_method->card_type->isEmpty()) {
      $avs_response_code_label = $this->buildAvsResponseCodeLabel('Z', $payment_method->card_type->value);
      $payment->setAvsResponseCodeLabel($avs_response_code_label);
    }
    $payment->save();
  }

  /**
   * {@inheritdoc}
   */
  public function buildAvsResponseCodeLabel($avs_response_code, $card_type) {
    if ($card_type == 'dinersclub' || $card_type == 'jcb') {
      if ($avs_response_code == 'Z') {
        return $this->t('Zip code.');
      }
      return NULL;
    }
    return parent::buildAvsResponseCodeLabel($avs_response_code, $card_type);
  }

}
