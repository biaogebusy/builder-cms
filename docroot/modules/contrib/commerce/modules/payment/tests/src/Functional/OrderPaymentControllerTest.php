<?php

namespace Drupal\Tests\commerce_payment\Functional;

use Drupal\Core\Url;
use Drupal\commerce_event_recorder_test\CommerceEventRecorder;
use Drupal\commerce_payment\Entity\Payment;

/**
 * Tests the merchant-facing "return" route for off-site payments.
 *
 * @group commerce
 */
class OrderPaymentControllerTest extends PaymentAdminTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'commerce_order',
    'commerce_product',
    'commerce_payment',
    'commerce_payment_test',
    'commerce_event_recorder_test',
  ];

  /**
   * The off-site payment gateway.
   *
   * @var \Drupal\commerce_payment\Entity\PaymentGatewayInterface
   */
  protected $paymentGateway;

  /**
   * The merchant return URL.
   *
   * @var string
   */
  protected string $returnUri;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->paymentGateway = $this->createEntity('commerce_payment_gateway', [
      'id' => 'offsite',
      'label' => 'Off-site',
      'plugin' => 'test_offsite',
    ]);

    $this->returnUri = Url::fromRoute('commerce_payment.order.merchant_return', [
      'commerce_order' => $this->order->id(),
      'commerce_payment_gateway' => $this->paymentGateway->id(),
    ])->toString();
  }

  /**
   * Tests a successful return, which creates a payment and redirects back.
   */
  public function testSuccessfulReturn() {
    $this->drupalGet($this->returnUri);
    $this->assertSession()->addressEquals($this->paymentUri);

    $payment = Payment::load(1);
    $this->assertNotNull($payment);
    $this->assertEquals($this->order->id(), $payment->getOrderId());

    $order = $this->reloadEntity($this->order);
    $this->assertEquals('offsite', $order->get('payment_gateway')->target_id);
    $this->assertFalse($order->isLocked());
  }

  /**
   * Tests that a non offsite payment gateway triggers an access exception.
   */
  public function testNonOffsiteGateway() {
    $manual_gateway = $this->createEntity('commerce_payment_gateway', [
      'id' => 'manual',
      'label' => 'Manual',
      'plugin' => 'manual',
    ]);
    $uri = Url::fromRoute('commerce_payment.order.merchant_return', [
      'commerce_order' => $this->order->id(),
      'commerce_payment_gateway' => $manual_gateway->id(),
    ])->toString();

    $this->drupalGet($uri);
    $this->assertSession()->statusCodeEquals(500);
    $this->assertNull(Payment::load(1));
    $this->assertFalse($this->reloadEntity($this->order)->isLocked());
  }

  /**
   * Tests that a NeedsRedirectException is silently ignored.
   */
  public function testOnReturnNeedsRedirect() {
    \Drupal::state()->set('test_offsite_on_return_exception', 'needs_redirect');

    $this->drupalGet($this->returnUri);
    $this->assertSession()->addressEquals($this->paymentUri);
    $this->assertNull(Payment::load(1));
    $this->assertFalse($this->reloadEntity($this->order)->isLocked());
  }

  /**
   * Tests that a PaymentGatewayException is handled and reported.
   */
  public function testOnReturnPaymentGatewayException() {
    \Drupal::state()->set('test_offsite_on_return_exception', 'payment_gateway');

    $this->drupalGet($this->returnUri);
    $this->assertSession()->addressEquals($this->paymentUri);
    $this->assertSession()->pageTextContains('Payment failed at the payment server. Please review your information and try again.');
    $this->assertNull(Payment::load(1));
    $this->assertFalse($this->reloadEntity($this->order)->isLocked());

    $expected = [
      [
        'order_id' => $this->order->id(),
        'payment_type' => '',
        'payment_gateway' => 'Off-site',
        'payment_method' => '',
      ],
    ];
    $this->assertSame($expected, \Drupal::state()->get(CommerceEventRecorder::STATE_KEY_PREFIX . 'onPaymentFailure'));
  }

  /**
   * Tests that an unexpected exception is handled and reported.
   */
  public function testOnReturnGenericException() {
    \Drupal::state()->set('test_offsite_on_return_exception', 'exception');

    $this->drupalGet($this->returnUri);
    $this->assertSession()->addressEquals($this->paymentUri);
    $this->assertSession()->pageTextContains('We encountered an issue recording your payment. Check the logs for details.');
    $this->assertNull(Payment::load(1));
    $this->assertFalse($this->reloadEntity($this->order)->isLocked());
  }

  /**
   * Tests that the route requires payment creation access.
   */
  public function testAccessDenied() {
    $this->drupalLogout();
    $this->drupalGet($this->returnUri);
    $this->assertSession()->statusCodeEquals(403);
  }

}
