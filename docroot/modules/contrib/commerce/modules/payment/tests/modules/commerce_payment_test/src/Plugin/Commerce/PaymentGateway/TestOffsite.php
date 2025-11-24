<?php

namespace Drupal\commerce_payment_test\Plugin\Commerce\PaymentGateway;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Attribute\CommercePaymentGateway;
use Drupal\commerce_payment_example\Plugin\Commerce\PaymentGateway\OffsiteRedirect;
use Drupal\commerce_payment_example\PluginForm\OffsiteRedirect\PaymentOffsiteForm;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides the Test off-site payment gateway.
 *
 * This is a copy of example_offsite_redirect with additional logic around
 * order data usage.
 */
#[CommercePaymentGateway(
  id: "test_offsite",
  label: new TranslatableMarkup("Test (Off-site redirect)"),
  display_label: new TranslatableMarkup("Test"),
  forms: [
    "offsite-payment" => PaymentOffsiteForm::class,
  ],
  payment_method_types: ["credit_card"],
  credit_card_types: [
    "amex", "dinersclub", "discover", "jcb", "maestro", "mastercard", "visa",
  ],
)]
class TestOffsite extends OffsiteRedirect {

  /**
   * The state key/value store.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    $instance = parent::create($container, $configuration, $plugin_id, $plugin_definition);
    $instance->state = $container->get('state');
    return $instance;
  }

  /**
   * {@inheritdoc}
   *
   * Adds data to the order and saves it. Done before or after the payment
   * is saved. Used by OffsiteOrderDataTest.
   */
  public function onReturn(OrderInterface $order, Request $request) {
    $order->setData('test_offsite', ['test' => TRUE]);

    if ($this->state->get('offsite_order_data_test_save') === 'before') {
      $order->save();
    }

    parent::onReturn($order, $request);

    if ($this->state->get('offsite_order_data_test_save') === 'after') {
      $order->save();
    }
  }

}
