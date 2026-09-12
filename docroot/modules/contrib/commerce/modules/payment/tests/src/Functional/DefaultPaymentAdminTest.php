<?php

namespace Drupal\Tests\commerce_payment\Functional;

use Drupal\commerce_payment\Entity\Payment;
use Drupal\commerce_price\Price;

/**
 * Tests the admin UI for payments of type 'payment_default'.
 *
 * @group commerce
 */
class DefaultPaymentAdminTest extends PaymentAdminTestBase {

  /**
   * An on-site payment gateway.
   *
   * @var \Drupal\commerce_payment\Entity\PaymentGatewayInterface
   */
  protected $paymentGateway;

  /**
   * Admin's payment method.
   *
   * @var \Drupal\commerce_payment\Entity\PaymentMethodInterface
   */
  protected $paymentMethod;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'commerce_order',
    'commerce_product',
    'commerce_payment',
    'commerce_payment_example',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $profile = $this->createEntity('profile', [
      'type' => 'customer',
      'address' => [
        'country_code' => 'US',
        'postal_code' => '53177',
        'locality' => 'Milwaukee',
        'address_line1' => 'Pabst Blue Ribbon Dr',
        'administrative_area' => 'WI',
        'given_name' => 'Frederick',
        'family_name' => 'Pabst',
      ],
      'uid' => $this->adminUser->id(),
    ]);

    $this->paymentGateway = $this->createEntity('commerce_payment_gateway', [
      'id' => 'example',
      'label' => 'Example',
      'plugin' => 'example_onsite',
    ]);
    $this->paymentMethod = $this->createEntity('commerce_payment_method', [
      'uid' => $this->loggedInUser->id(),
      'type' => 'credit_card',
      'payment_gateway' => 'example',
      'billing_profile' => $profile,
    ]);

    $details = [
      'type' => 'visa',
      'number' => '4111111111111111',
      'expiration' => ['month' => '01', 'year' => date('Y') + 1],
    ];
    $this->paymentGateway->getPlugin()->createPaymentMethod($this->paymentMethod, $details);
  }

  /**
   * Tests the Payments tab.
   */
  public function testPaymentTab() {
    // Confirm that the tab is visible on the order page.
    $this->drupalGet($this->order->toUrl());
    $this->assertSession()->linkExists('Payments');

    // Confirm that a payment is visible.
    $this->createEntity('commerce_payment', [
      'payment_gateway' => $this->paymentGateway->id(),
      'payment_method' => $this->paymentMethod->id(),
      'order_id' => $this->order->id(),
      'amount' => new Price('10', 'USD'),
    ]);
    $this->drupalGet($this->paymentUri);
    $this->assertSession()->pageTextContains('$10.00');
    $this->assertSession()->pageTextContains($this->paymentGateway->label());
    $this->assertSession()->pageTextContains('Order balance $10.00');

    // Test that order balance is updated when new items is added/removed.
    /** @var \Drupal\commerce_order\Entity\OrderItem $new_item */
    $new_item = $this->createEntity('commerce_order_item', [
      'type' => 'test',
      'quantity' => 1,
      'unit_price' => new Price('15', 'USD'),
    ]);
    $new_item->save();
    $this->order->addItem($new_item);
    $this->order->save();
    $this->drupalGet($this->paymentUri);
    $this->assertSession()->pageTextContains('Order balance $25.00');

    // Remove item and check the balance again.
    $this->order->removeItem($new_item);
    $this->order->save();
    $this->drupalGet($this->paymentUri);
    $this->assertSession()->pageTextContains('Order balance $10.00');

    // Confirm that the payment is visible even if the gateway was deleted.
    $this->paymentGateway->delete();
    $this->drupalGet($this->paymentUri);
    $this->assertSession()->pageTextContains('$10.00');
    $this->assertSession()->pageTextNotContains($this->paymentGateway->label());
  }

  /**
   * Tests creating a payment for an order.
   */
  public function testPaymentCreation() {
    $this->drupalGet($this->paymentUri);
    $this->getSession()->getPage()->clickLink('Add payment');
    $this->assertSession()->addressEquals($this->paymentUri . '/add');
    $this->pageContainsOrderDetails();
    $this->assertSession()->pageTextContains('Visa ending in 1111');
    $this->assertSession()->checkboxChecked('payment_option');

    $this->submitForm(['amount[number]' => '100'], 'Add payment');
    $this->assertSession()->addressEquals($this->paymentUri);
    $this->assertSession()->elementContains('css', 'table tbody tr td:nth-child(2)', 'Completed');

    $this->order = $this->reloadEntity($this->order);
    \Drupal::entityTypeManager()->getStorage('commerce_payment')->resetCache([1]);
    /** @var \Drupal\commerce_payment\Entity\PaymentInterface $payment */
    $payment = Payment::load(1);
    $this->assertEquals($this->order->id(), $payment->getOrderId());
    $billing_profile = $this->order->getBillingProfile();
    $this->assertNotEmpty($billing_profile);
    $this->assertEquals($payment->getPaymentMethod()->id(), $this->order->get('payment_method')->target_id);
    $this->assertEquals($billing_profile->get('address')->first()->toArray(), $payment->getPaymentMethod()->getBillingProfile()->get('address')->first()->toArray());
    $this->assertEquals('100.00', $payment->getAmount()->getNumber());
    $this->assertNotEmpty($payment->getCompletedTime());
    $this->assertEquals('A', $payment->getAvsResponseCode());
    $this->assertEquals('Address', $payment->getAvsResponseCodeLabel());

    // Test if the link is not accessible when the order balance is null.
    $this->order->setItems([])->save();
    $this->drupalGet($this->paymentUri);
    $this->assertSession()->linkNotExists('Add payment');
    $this->drupalGet($this->paymentUri . '/add');
    $this->assertSession()->statusCodeEquals(403);
  }

  /**
   * Tests creating a partial payment.
   */
  public function testPartialPaymentCreation() {
    $this->drupalGet($this->paymentUri);
    $this->getSession()->getPage()->clickLink('Add payment');
    $this->assertSession()->addressEquals($this->paymentUri . '/add');

    // Confirm that table with order items is shown.
    foreach ($this->order->getItems() as $order_item) {
      $this->assertSession()->elementTextContains(
        'css',
        'table[data-drupal-selector="edit-order-summary-order-items"]',
        $order_item->label(),
      );
    }
    $this->assertSession()->pageTextContains('Subtotal $10.00');
    $this->assertSession()->pageTextContains('Total $10.00');
    $this->assertSession()->pageTextContains('Order balance $10.00');
    $this->assertSession()->fieldValueEquals('amount[number]', number_format($this->order->getBalance()->getNumber(), 2));

    // Partially pay for the order
    $this->submitForm(['amount[number]' => '6.75'], 'Add payment');
    $this->assertSession()->addressEquals($this->paymentUri);
    $this->assertSession()->elementContains('css', 'table tbody tr td:nth-child(2)', 'Completed');
    $this->assertSession()->pageTextContains('Total paid $6.75');
    $this->assertSession()->pageTextContains('Order balance $3.25');

    // Add payment for the rest order balance.
    $this->order = $this->reloadEntity($this->order);
    $this->drupalGet($this->paymentUri . '/add');
    $this->assertSession()->pageTextContains('Subtotal $10.00');
    $this->assertSession()->pageTextContains('Total $10.00');
    $this->assertSession()->pageTextContains('Total paid $6.75');
    $this->assertSession()->pageTextContains('Order balance $3.25');
    $this->assertSession()->fieldValueEquals('amount[number]', number_format($this->order->getBalance()->getNumber(), 2));

    // Confirm that order is fully paid.
    $this->submitForm([], 'Add payment');
    $this->assertSession()->addressEquals($this->paymentUri);
    $this->assertSession()->pageTextContains('Total paid $10.00');
    $this->assertSession()->pageTextContains('Order balance $0.00');

    // Confirm that both payments presented in the table.
    /** @var \Drupal\commerce_payment\Entity\PaymentInterface[] $payments */
    $payments = $this->container->get('entity_type.manager')
      ->getStorage('commerce_payment')
      ->loadByProperties(['order_id' => $this->order->id()]);
    $payments = array_reverse($payments);
    $payment_rows = $this->xpath('//table/tbody/tr');
    $this->assertCount(2, $payment_rows);
    foreach ($payment_rows as $delta => $payment_row) {
      $row_text = $payment_row->getText();
      $payment = $payments[$delta];

      $this->assertStringContainsString($payment->get('amount')->formatted, $row_text);
      $this->assertStringContainsString(sprintf('Refunded: %s', $payment->get('refunded_amount')->formatted), $row_text);
      $this->assertStringContainsString(sprintf('AVS response: [%s] %s', $payment->getAvsResponseCode(), $payment->getAvsResponseCodeLabel()), $row_text);
      $this->assertStringContainsString('Completed', $row_text);
    }
  }

  /**
   * Tests capturing a payment after creation.
   */
  public function testPaymentCapture() {
    $payment = $this->createEntity('commerce_payment', [
      'payment_gateway' => $this->paymentGateway->id(),
      'payment_method' => $this->paymentMethod->id(),
      'order_id' => $this->order->id(),
      'amount' => new Price('10', 'USD'),
    ]);
    $this->paymentGateway->getPlugin()->createPayment($payment, FALSE);

    $this->drupalGet($this->paymentUri);
    $this->assertSession()->pageTextContains('Authorization');
    $this->drupalGet($this->paymentUri . '/' . $payment->id() . '/operation/capture');
    $this->pageContainsOrderDetails();
    $this->submitForm(['payment[amount][number]' => '10'], 'Capture');

    \Drupal::entityTypeManager()->getStorage('commerce_payment')->resetCache([$payment->id()]);
    $payment = Payment::load($payment->id());
    $this->assertSession()->addressEquals($this->paymentUri);
    $this->assertSession()->pageTextNotContains('Authorization');
    $this->assertSession()->elementContains('css', 'table tbody tr td:nth-child(2)', 'Completed');
    $date_formatter = $this->container->get('date.formatter');
    $this->assertSession()->elementContains('css', 'table tbody tr td:nth-child(5)', $date_formatter->format($payment->getCompletedTime(), 'short'));

    $this->assertEquals($payment->getState()->getLabel(), 'Completed');
  }

  /**
   * Tests refunding a payment after capturing.
   */
  public function testPaymentRefund() {
    $payment = $this->createEntity('commerce_payment', [
      'payment_gateway' => $this->paymentGateway->id(),
      'payment_method' => $this->paymentMethod->id(),
      'order_id' => $this->order->id(),
      'amount' => new Price('10', 'USD'),
    ]);

    $this->paymentGateway->getPlugin()->createPayment($payment, TRUE);

    $this->drupalGet($this->paymentUri);
    $this->assertSession()->elementContains('css', 'table tbody tr td:nth-child(2)', 'Completed');

    $this->drupalGet($this->paymentUri . '/' . $payment->id() . '/operation/refund');
    $this->pageContainsOrderDetails();
    $this->submitForm(['payment[amount][number]' => '10'], 'Refund');
    $this->assertSession()->addressEquals($this->paymentUri);
    $this->assertSession()->elementNotContains('css', 'table tbody tr td:nth-child(2)', 'Completed');
    $this->assertSession()->pageTextContains('Refunded');

    \Drupal::entityTypeManager()->getStorage('commerce_payment')->resetCache([$payment->id()]);
    $payment = Payment::load($payment->id());
    $this->assertEquals($payment->getState()->getLabel(), 'Refunded');
  }

  /**
   * Tests voiding a payment after creation.
   */
  public function testPaymentVoid() {
    $payment = $this->createEntity('commerce_payment', [
      'payment_gateway' => $this->paymentGateway->id(),
      'payment_method' => $this->paymentMethod->id(),
      'order_id' => $this->order->id(),
      'amount' => new Price('10', 'USD'),
    ]);

    $this->paymentGateway->getPlugin()->createPayment($payment, FALSE);

    $this->drupalGet($this->paymentUri);
    $this->assertSession()->pageTextContains('Authorization');

    $this->drupalGet($this->paymentUri . '/' . $payment->id() . '/operation/void');
    $this->pageContainsOrderDetails(FALSE);
    $this->assertSession()->pageTextContains('Are you sure you want to void the 10 USD payment?');
    $this->getSession()->getPage()->pressButton('Void');
    $this->assertSession()->addressEquals($this->paymentUri);
    $this->assertSession()->pageTextContains('Authorization (Voided)');

    \Drupal::entityTypeManager()->getStorage('commerce_payment')->resetCache([$payment->id()]);
    $payment = Payment::load($payment->id());
    $this->assertEquals($payment->getState()->getLabel(), 'Authorization (Voided)');
  }

  /**
   * Tests deleting a payment after creation.
   */
  public function testPaymentDelete() {
    $payment = $this->createEntity('commerce_payment', [
      'payment_gateway' => $this->paymentGateway->id(),
      'payment_method' => $this->paymentMethod->id(),
      'order_id' => $this->order->id(),
      'amount' => new Price('10', 'USD'),
    ]);

    $this->paymentGateway->getPlugin()->createPayment($payment, FALSE);

    $this->drupalGet($this->paymentUri);
    $this->assertSession()->pageTextContains('Authorization');

    $this->drupalGet($this->paymentUri . '/' . $payment->id() . '/delete');
    $this->getSession()->getPage()->pressButton('Delete');
    $this->assertSession()->addressEquals($this->paymentUri);
    $this->assertSession()->pageTextNotContains('Authorization');

    \Drupal::entityTypeManager()->getStorage('commerce_payment')->resetCache([$payment->id()]);
    $payment = Payment::load($payment->id());
    $this->assertNull($payment);
  }

}
