<?php

namespace Drupal\Tests\commerce_payment\FunctionalJavascript;

use Drupal\commerce_payment\Entity\PaymentGateway;
use Drupal\Core\Url;
use Drupal\Tests\commerce\FunctionalJavascript\CommerceWebDriverTestBase;

/**
 * Tests the payment gateway form.
 *
 * @group commerce
 */
class PaymentGatewayFormTest extends CommerceWebDriverTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'commerce_payment',
    'commerce_payment_example',
  ];

  /**
   * {@inheritdoc}
   */
  protected function getAdministratorPermissions() {
    return array_merge([
      'administer commerce_payment_gateway',
    ], parent::getAdministratorPermissions());
  }

  /**
   * Tests using the payment gateway add form.
   */
  public function testPaymentGatewayCreation() {
    $this->drupalGet(Url::fromRoute('entity.commerce_payment_gateway.add_form')->toString());
    $expected_options = [
      'Example (Off-site redirect with stored payment methods)',
      'Example (Off-site redirect)',
      'Example (On-site)',
      'Manual',
    ];
    $page = $this->getSession()->getPage();
    foreach ($expected_options as $expected_option) {
      $radio_button = $page->findField($expected_option);
      $this->assertNotNull($radio_button);
    }
    $default_radio_button = $page->findField('Example (Off-site redirect with stored payment methods)');
    $this->assertNotEmpty($default_radio_button->getAttribute('checked'));
    $this->assertSession()->fieldExists('Name');
    $this->assertSession()->fieldExists('id');
    $this->assertSession()->fieldExists('Redirect via POST (automatic)');

    $radio_button = $this->getSession()->getPage()->findField('On-site');
    $radio_button->click();
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->fieldExists('Name');
    $this->assertSession()->fieldExists('API key');

    $page = $this->getSession()->getPage();
    $page->fillField('label', 'My onsite name');
    $page->fillField('configuration[example_onsite][api_key]', 'MyAPIKey');
    $this->submitForm([], 'Save');
    // machine_id causes an issue I could not solve. Tried the same way as
    // NumberPatternTest does it, it did not work. This ugliness below however
    // does.
    $page->fillField('id', 'my_onsite_name');
    $this->submitForm([], 'Save');

    $this->drupalGet('admin/commerce/config/payment-gateways');

    $entity_type_manager = $this->container->get('entity_type.manager');
    /** @var \Drupal\commerce_payment\Entity\PaymentGatewayInterface $onsite_payment_gateway */
    $onsite_payment_gateway = $entity_type_manager->getStorage('commerce_payment_gateway')->load('my_onsite_name');
    $this->assertEquals('MyAPIKey', $onsite_payment_gateway->getPluginConfiguration()['api_key']);
    $this->assertEquals('My onsite name', $onsite_payment_gateway->label());
    $this->assertEquals('test', $onsite_payment_gateway->getPlugin()->getMode());
  }

  /**
   * Tests using the payment gateway add form.
   */
  public function testPaymentGatewayEditing() {
    $values = [
      'id' => 'onsite',
      'label' => 'On-site',
      'plugin' => 'example_onsite',
      'configuration' => [
        'api_key' => 'MyAPIkey',
        'payment_method_types' => ['credit_card'],
        'mode' => 'test',
      ],
    ];
    /** @var \Drupal\commerce_payment\Entity\PaymentGatewayInterface $payment_gateway */
    $payment_gateway = PaymentGateway::create($values);
    $payment_gateway->save();

    $this->drupalGet(Url::fromRoute('entity.commerce_payment_gateway.edit_form', ['commerce_payment_gateway' => 'onsite'])->toString());

    $page = $this->getSession()->getPage();
    $default_radio_button = $page->findField('Example (On-site)');
    $this->assertNotEmpty($default_radio_button->getAttribute('checked'));
    $this->assertEquals('disabled', $default_radio_button->getAttribute('disabled'));
    $this->assertSession()->fieldNotExists('Redirect via POST (automatic)');

    $default_radio_button = $page->findField('Test');
    $this->assertNotEmpty($default_radio_button->getAttribute('checked'));

    $this->assertSession()->fieldValueEquals('Name', $values['label']);
    $this->assertSession()->fieldValueEquals('configuration[example_onsite][api_key]', $values['configuration']['api_key']);

    $edit = [
      'label' => 'Edited onsite name',
      'configuration[example_onsite][api_key]' => 'Edited MyAPIkey',
      'configuration[example_onsite][mode]' => 'live',
    ];
    $this->submitForm($edit, 'Save');
    $entity_type_manager = $this->container->get('entity_type.manager');
    /** @var \Drupal\commerce_payment\Entity\PaymentGatewayInterface $onsite_payment_gateway */
    $onsite_payment_gateway = $entity_type_manager->getStorage('commerce_payment_gateway')->load('onsite');
    $this->assertEquals($edit['configuration[example_onsite][api_key]'], $onsite_payment_gateway->getPluginConfiguration()['api_key']);
    $this->assertEquals($edit['label'], $onsite_payment_gateway->label());
    $this->assertEquals($edit['configuration[example_onsite][mode]'], $onsite_payment_gateway->getPlugin()->getMode());
  }

}
