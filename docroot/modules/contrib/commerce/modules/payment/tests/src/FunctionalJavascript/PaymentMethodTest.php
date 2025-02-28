<?php

namespace Drupal\Tests\commerce_payment\FunctionalJavascript;

use Drupal\commerce_payment\Entity\PaymentMethod;
use Drupal\Tests\commerce\FunctionalJavascript\CommerceWebDriverTestBase;

/**
 * Tests the payment method UI.
 *
 * @group commerce
 */
class PaymentMethodTest extends CommerceWebDriverTestBase {

  /**
   * A normal user with minimum permissions.
   *
   * @var \Drupal\User\UserInterface
   */
  protected $user;

  /**
   * The payment method collection url.
   *
   * @var string
   */
  protected $collectionUrl;

  /**
   * An on-site payment gateway.
   *
   * @var \Drupal\commerce_payment\Entity\PaymentGatewayInterface
   */
  protected $paymentGateway;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'commerce_payment',
    'commerce_payment_example',
    'commerce_payment_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $permissions = [
      'manage own commerce_payment_method',
    ];
    $this->user = $this->drupalCreateUser($permissions);
    $this->drupalLogin($this->user);
    $this->collectionUrl = 'user/' . $this->user->id() . '/payment-methods';

    /** @var \Drupal\commerce_payment\Entity\PaymentGatewayInterface $payment_gateway */
    $this->paymentGateway = $this->createEntity('commerce_payment_gateway', [
      'id' => 'example',
      'label' => 'Example',
      'plugin' => 'example_onsite',
    ]);
  }

  /**
   * Tests the payment method add form in case of multiple gateways.
   */
  public function testPaymentMethodCreateWithMultipleGateways() {
    $this->createEntity('commerce_payment_gateway', [
      'id' => 'onsite_2',
      'label' => 'Onsite Example 2',
      'plugin' => 'test_onsite',
    ]);
    $default_address = [
      'country_code' => 'US',
      'administrative_area' => 'SC',
      'locality' => 'Greenville',
      'postal_code' => '29616',
      'address_line1' => '9 Drupal Ave',
      'given_name' => 'Bryan',
      'family_name' => 'Centarro',
    ];
    $this->createEntity('profile', [
      'type' => 'customer',
      'uid' => $this->user->id(),
      'address' => $default_address,
    ]);

    $this->drupalGet($this->collectionUrl . '/add');
    $this->assertSession()->fieldExists('payment_method');
    $this->assertSession()->elementExists('css', '[value="new--credit_card--example"]');
    $this->assertSession()->elementExists('css', '[value="new--credit_card--onsite_2"]');
    $radio_button = $this->getSession()->getPage()->findField('Credit card (Test)');
    $radio_button->click();
    $this->assertSession()->assertWaitOnAjaxRequest();
    $form_values = [
      'add_payment_method[payment_details][number]' => '4111111111111111',
      'add_payment_method[payment_details][expiration][month]' => '01',
      'add_payment_method[payment_details][expiration][year]' => date('Y') + 1,
      'add_payment_method[payment_details][security_code]' => '111',
    ];
    $this->submitForm($form_values, 'Save');
    $this->assertSession()->addressEquals($this->collectionUrl);
    $this->assertSession()->pageTextContains('Visa ending in 1111 saved to your payment methods.');

    $payment_method = PaymentMethod::load(1);
    $this->assertEquals($payment_method->getPaymentGateway()->getPluginId(), 'test_onsite');
    $this->assertEquals($payment_method->getPaymentGateway()->id(), 'onsite_2');
  }

}
