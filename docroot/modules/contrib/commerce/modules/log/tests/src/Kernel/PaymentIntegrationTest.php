<?php

namespace Drupal\Tests\commerce_log\Kernel;

use Drupal\commerce_log\LogStorageInterface;
use Drupal\commerce_log\LogViewBuilder;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\Payment;
use Drupal\commerce_payment\Entity\PaymentGateway;
use Drupal\commerce_payment\Entity\PaymentMethod;
use Drupal\commerce_price\Price;
use Drupal\commerce_product\Entity\Product;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\profile\Entity\Profile;
use Drupal\Tests\commerce_order\Kernel\OrderKernelTestBase;

/**
 * Tests integration with payment events.
 *
 * @group commerce
 */
class PaymentIntegrationTest extends OrderKernelTestBase {

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
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'address',
    'commerce_product',
    'commerce_payment',
    'commerce_payment_example',
    'commerce_log',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('commerce_log');
    $this->installEntitySchema('commerce_payment');
    $this->installEntitySchema('commerce_payment_method');
    $this->installConfig('commerce_payment');

    $this->logStorage = $this->container->get('entity_type.manager')
      ->getStorage('commerce_log');
    $this->logViewBuilder = $this->container->get('entity_type.manager')
      ->getViewBuilder('commerce_log');

    $payment_gateway = PaymentGateway::create([
      'id' => 'example',
      'label' => 'Example',
      'plugin' => 'example_onsite',
    ]);
    $payment_gateway->save();

    $user = $this->createUser(['mail' => $this->randomString() . '@example.com']);

    $profile = Profile::create([
      'type' => 'customer',
    ]);
    $profile->save();
    $profile = $this->reloadEntity($profile);

    $payment_method_active = PaymentMethod::create([
      'uid' => $user->id(),
      'type' => 'credit_card',
      'payment_gateway' => 'example',
      'card_type' => 'visa',
      'card_number' => '1111',
      'billing_profile' => $profile,
      'reusable' => TRUE,
    ]);
    $payment_method_active->save();

    $product = Product::create([
      'type' => 'default',
      'title' => 'Default testing product',
    ]);
    $product->save();

    $variation1 = ProductVariation::create([
      'type' => 'default',
      'sku' => 'TEST_' . strtolower($this->randomMachineName()),
      'title' => $this->randomString(),
      'status' => 1,
      'price' => new Price('12.00', 'USD'),
    ]);
    $variation1->save();
    $product->addVariation($variation1)->save();

    /** @var \Drupal\commerce_order\OrderItemStorageInterface $order_item_storage */
    $order_item_storage = $this->container->get('entity_type.manager')
      ->getStorage('commerce_order_item');

    $order_item1 = $order_item_storage->createFromPurchasableEntity($variation1);
    $order_item1->save();
    $order = Order::create([
      'type' => 'default',
      'store_id' => $this->store->id(),
      'state' => 'draft',
      'mail' => $user->getEmail(),
      'uid' => $user->id(),
      'ip_address' => '127.0.0.1',
      'order_number' => '6',
      'billing_profile' => $profile,
      'order_items' => [$order_item1],
    ]);
    $order->save();
    $this->order = $this->reloadEntity($order);
  }

  /**
   * Tests that a log is generated on payment insert, update and delete.
   */
  public function testPaymentLogs(): void {
    // Create a dummy payment.
    $payment = Payment::create([
      'order_id' => $this->order->id(),
      'payment_gateway' => 'example',
      'payment_method' => 1,
      'remote_id' => '123456',
      'amount' => [
        'number' => '39.99',
        'currency_code' => 'USD',
      ],
      'state' => 'new',
      'test' => TRUE,
    ]);

    // Check the payment added log.
    $payment->save();
    $logs = $this->logStorage->loadMultipleByEntity($this->order);
    $this->assertEquals(1, count($logs));
    $log = $logs[1];
    $build = $this->logViewBuilder->view($log);
    $this->render($build);
    $this->assertText("Payment added via Example for $39.99 using Visa ending in 1111. State: New. Transaction ID: 123456.");

    // Reload the payment.
    $this->reloadEntity($payment);

    // Check the payment become authorized log.
    $payment->setState('authorization');
    $payment->save();
    $logs = $this->logStorage->loadMultipleByEntity($this->order);
    $this->assertEquals(2, count($logs));
    $log = $logs[2];
    $build = $this->logViewBuilder->view($log);
    $this->render($build);
    $this->assertText('Payment authorized via Example for $39.99 using Visa ending in 1111. Transaction ID: 123456.');

    // Reload the payment.
    $this->reloadEntity($payment);

    // Check the payment become completed log.
    $payment->setState('completed');
    $payment->save();
    $logs = $this->logStorage->loadMultipleByEntity($this->order);
    $this->assertEquals(3, count($logs));
    $log = $logs[3];
    $build = $this->logViewBuilder->view($log);
    $this->render($build);
    $this->assertText('Payment captured via Example for $39.99 using Visa ending in 1111. Transaction ID: 123456.');

    // Reload the payment.
    $this->reloadEntity($payment);

    // Check the payment updated log.
    $payment->setRefundedAmount(new Price('10.00', 'USD'));
    $payment->setState('partially_refunded');
    $payment->save();
    $logs = $this->logStorage->loadMultipleByEntity($this->order);
    $this->assertEquals(4, count($logs));
    $log = $logs[4];
    $build = $this->logViewBuilder->view($log);
    $this->render($build);
    $this->assertText('Payment updated. Payment balance: $29.99. State: Partially refunded. Transaction ID: 123456.');

    // Reload the payment.
    $this->reloadEntity($payment);

    // Check the payment deleted log.
    $payment->delete();
    $logs = $this->logStorage->loadMultipleByEntity($this->order);
    $this->assertEquals(5, count($logs));
    $log = $logs[5];
    $build = $this->logViewBuilder->view($log);
    $this->render($build);
    $this->assertText('Payment deleted: $29.99. [Visa ending in 1111]. Transaction ID: 123456.');
  }

  /**
   * Tests that a log is generated when an authorized payments is added.
   */
  public function testAuthorizedPaymentCreationLogs(): void {
    // Create a dummy payment.
    $payment = Payment::create([
      'order_id' => $this->order->id(),
      'payment_gateway' => 'example',
      'payment_method' => 1,
      'remote_id' => '123456',
      'amount' => [
        'number' => '39.99',
        'currency_code' => 'USD',
      ],
      'state' => 'authorization',
      'test' => TRUE,
    ]);

    // Check the payment added log.
    $payment->save();
    $logs = $this->logStorage->loadMultipleByEntity($this->order);
    $this->assertEquals(1, count($logs));
    $log = $logs[1];
    $build = $this->logViewBuilder->view($log);
    $this->render($build);
    $this->assertText('Payment authorized via Example for $39.99 using Visa ending in 1111. Transaction ID: 123456.');
  }

  /**
   * Tests that a log is generated when a completed payment is inserted.
   */
  public function testCompletedPaymentCreationLogs(): void {
    // Create a dummy payment.
    $payment = Payment::create([
      'order_id' => $this->order->id(),
      'payment_gateway' => 'example',
      'payment_method' => 1,
      'remote_id' => '123456',
      'amount' => [
        'number' => '39.99',
        'currency_code' => 'USD',
      ],
      'state' => 'completed',
      'test' => TRUE,
    ]);

    // Check the payment added log.
    $payment->save();
    $logs = $this->logStorage->loadMultipleByEntity($this->order);
    $this->assertEquals(1, count($logs));
    $log = $logs[1];
    $build = $this->logViewBuilder->view($log);
    $this->render($build);
    $this->assertText('Payment captured via Example for $39.99 using Visa ending in 1111. Transaction ID: 123456.');
  }

  /**
   * Tests that a log is created when a payment without method is saved.
   */
  public function testPaymentWithoutMethodLog(): void {
    // Create a dummy payment without payment method.
    $payment = Payment::create([
      'order_id' => $this->order->id(),
      'payment_gateway' => 'example',
      'remote_id' => '123456',
      'amount' => [
        'number' => '39.99',
        'currency_code' => 'USD',
      ],
      'state' => 'new',
      'test' => TRUE,
    ]);
    // Check that log was added on creation.
    $payment->save();
    $logs = $this->logStorage->loadMultipleByEntity($this->order);
    $this->assertEquals(1, count($logs));
    $log = $logs[1];
    $build = $this->logViewBuilder->view($log);
    $this->render($build);
    $this->assertText('Payment added via Example for $39.99. State: New. Transaction ID: 123456.');

    // Reload the payment.
    $this->reloadEntity($payment);

    // Check that log was added on update.
    $payment->setState('completed');
    $payment->save();
    $logs = $this->logStorage->loadMultipleByEntity($this->order);
    $this->assertEquals(2, count($logs));
    $log = $logs[2];
    $build = $this->logViewBuilder->view($log);
    $this->render($build);
    $this->assertText('Payment captured via Example for $39.99. Transaction ID: 123456.');

    // Reload the payment.
    $this->reloadEntity($payment);

    // Check the payment deleted log.
    $payment->delete();
    $logs = $this->logStorage->loadMultipleByEntity($this->order);
    $this->assertEquals(3, count($logs));
    $log = $logs[3];
    $build = $this->logViewBuilder->view($log);
    $this->render($build);
    $this->assertText('Payment deleted: $39.99. Transaction ID: 123456.');
  }

  /**
   * Tests manual payments.
   */
  public function testManualPayments(): void {
    $payment_gateway = PaymentGateway::create([
      'id' => 'manual',
      'label' => 'Manual',
      'plugin' => 'manual',
    ]);
    $payment_gateway->save();

    // Create a dummy payment without payment method.
    $payment = Payment::create([
      'order_id' => $this->order->id(),
      'payment_gateway' => 'manual',
      'amount' => [
        'number' => '39.99',
        'currency_code' => 'USD',
      ],
      'state' => 'pending',
      'test' => TRUE,
    ]);
    // Check that log was added on creation.
    $payment->save();
    $logs = $this->logStorage->loadMultipleByEntity($this->order);
    $this->assertEquals(1, count($logs));
    $log = $logs[1];
    $build = $this->logViewBuilder->view($log);
    $this->render($build);
    $this->assertText('Payment added via Manual for $39.99. State: Pending.');

    // Reload the payment.
    $this->reloadEntity($payment);

    // Check that log was added on update.
    $payment->setState('completed');
    $payment->save();

    $logs = $this->logStorage->loadMultipleByEntity($this->order);
    $this->assertEquals(2, count($logs));
    $log = $logs[2];
    $build = $this->logViewBuilder->view($log);
    $this->render($build);
    $this->assertText('Payment received via Manual for $39.99.');
  }

}
