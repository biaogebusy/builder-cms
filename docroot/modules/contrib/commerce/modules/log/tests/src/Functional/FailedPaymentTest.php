<?php

namespace Drupal\Tests\commerce_log\Functional;

use Drupal\Tests\commerce_order\Functional\OrderBrowserTestBase;
use Drupal\commerce_event_recorder_test\CommerceEventRecorder;
use Drupal\commerce_log\LogStorageInterface;
use Drupal\commerce_log\LogViewBuilder;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\profile\Entity\Profile;

/**
 * Test logging for failed payments.
 *
 * @group commerce
 */
class FailedPaymentTest extends OrderBrowserTestBase {

  /**
   * A sample order.
   */
  protected OrderInterface $order;

  /**
   * The log storage.
   */
  protected LogStorageInterface $logStorage;

  /**
   * The log view builder.
   */
  protected LogViewBuilder $logViewBuilder;

  /**
   * The default profile's address.
   */
  protected array $defaultAddress = [
    'country_code' => 'US',
    'administrative_area' => 'SC',
    'locality' => 'Greenville',
    'postal_code' => '53140',
    'address_line1' => '9 Drupal Ave',
    'given_name' => 'Bryan',
    'family_name' => 'Centarro',
  ];

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'commerce_product',
    'commerce_cart',
    'commerce_checkout',
    'commerce_payment',
    'commerce_payment_example',
    'commerce_log',
    'commerce_event_recorder_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function getAdministratorPermissions(): array {
    return array_merge([
      'administer profile',
    ], parent::getAdministratorPermissions());
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $entity_type_manager = $this->container->get('entity_type.manager');
    $this->logStorage = $entity_type_manager->getStorage('commerce_log');
    $this->logViewBuilder = $entity_type_manager->getViewBuilder('commerce_log');

    /** @var \Drupal\commerce_payment\Entity\PaymentGatewayInterface $payment_gateway */
    $payment_gateway = $this->createEntity('commerce_payment_gateway', [
      'id' => 'example',
      'label' => 'Example',
      'plugin' => 'example_onsite',
    ]);
    $payment_gateway->save();

    $user = $this->adminUser;

    $profile = Profile::create([
      'type' => 'customer',
      'uid' => $user->id(),
      'address' => $this->defaultAddress,
    ]);
    $profile->save();
    $profile = $this->reloadEntity($profile);

    $payment_method_active = $this->createEntity('commerce_payment_method', [
      'uid' => $user->id(),
      'type' => 'credit_card',
      'payment_gateway' => 'example',
      'card_type' => 'visa',
      'card_number' => '1111',
      'billing_profile' => $profile,
      'reusable' => TRUE,
    ]);
    $payment_method_active->save();

    /** @var \Drupal\commerce_order\OrderItemStorageInterface $order_item_storage */
    $order_item_storage = $entity_type_manager
      ->getStorage('commerce_order_item');

    $order_item = $order_item_storage->createFromPurchasableEntity($this->variation);
    $order_item->save();
    $order = $this->createEntity('commerce_order', [
      'type' => 'default',
      'store_id' => $this->store->id(),
      'state' => 'draft',
      'mail' => $user->getEmail(),
      'uid' => $user->id(),
      'ip_address' => '127.0.0.1',
      'order_number' => '6',
      'billing_profile' => $profile,
      'order_items' => [$order_item],
    ]);
    $order->save();
    $this->order = $this->reloadEntity($order);
  }

  /**
   * Create logs for failed payment.
   */
  public function testFailedPayment(): void {
    $this->drupalGet("checkout/{$this->order->id()}");
    $this->submitForm([], 'Continue to review');
    $this->submitForm([], 'Pay and complete purchase');

    $this->assertSession()->pageTextContains('We encountered an error processing your payment method. Please verify your details and try again.');

    // Ensure the expected payment failure event has been recorded.
    $expected = [
      [
        'order_id' => '1',
        'payment_type' => 'Default',
        'payment_gateway' => 'Example',
        'payment_method' => 'Visa ending in 1111',
      ],
    ];
    $this->assertSame($expected, \Drupal::state()->get(CommerceEventRecorder::STATE_KEY_PREFIX . 'onPaymentFailure'));

    // Check the payment failed log.
    $logs = $this->logStorage->loadMultipleByEntity($this->order);
    $this->assertEquals(1, count($logs));
    /** @var \Drupal\commerce_log\Entity\Log $log */
    $log = reset($logs);
    // Ensure that payment method types with
    // \Drupal\commerce_payment\FailedPaymentDetailsInterface have the
    // additional data.
    $this->assertSame('visa', $log->getParams()['card_type']);

    $this->drupalGet($this->order->toUrl()->toString());
    $this->assertSession()->pageTextContains('Payment failed via Example for $999.00 using Visa ending in 1111.Message: The payment was declined.');
  }

}
