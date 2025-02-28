<?php

namespace Drupal\Tests\commerce_order\FunctionalJavascript;

use Drupal\commerce_order\Adjustment;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_price\Price;
use Drupal\Core\Test\AssertMailTrait;
use Drupal\profile\Entity\Profile;

/**
 * Tests the order admin UI.
 *
 * @group commerce
 */
class OrderAdminTest extends OrderWebDriverTestBase {

  use AssertMailTrait;

  /**
   * The default profile's address.
   *
   * @var array
   */
  protected $defaultAddress = [
    'country_code' => 'US',
    'administrative_area' => 'SC',
    'locality' => 'Greenville',
    'postal_code' => '29616',
    'address_line1' => '9 Drupal Ave',
    'given_name' => 'Bryan',
    'family_name' => 'Centarro',
  ];

  /**
   * The default profile.
   *
   * @var \Drupal\profile\Entity\ProfileInterface
   */
  protected $defaultProfile;

  /**
   * The second variation.
   *
   * @var \Drupal\commerce_product\Entity\ProductVariationInterface
   */
  protected $secondVariation;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->store->set('billing_countries', ['FR', 'US']);
    $this->store->save();

    $this->defaultProfile = Profile::create([
      'type' => 'customer',
      'uid' => $this->adminUser,
      'address' => $this->defaultAddress,
    ]);
    $this->defaultProfile->save();

    // Create a product variation.
    $this->secondVariation = $this->createEntity('commerce_product_variation', [
      'type' => 'default',
      'sku' => $this->randomMachineName(),
      'price' => [
        'number' => 5.55,
        'currency_code' => 'USD',
      ],
    ]);
    $product = $this->variation->getProduct();
    $product->addVariation($this->secondVariation);
    $product->save();
  }

  /**
   * Tests creating an order.
   */
  public function testCreateOrder() {
    // Create an order through the add form.
    $this->drupalGet('/admin/commerce/orders');
    $this->getSession()->getPage()->clickLink('Create a new order');
    $user = $this->loggedInUser->getAccountName() . ' (' . $this->loggedInUser->id() . ')';
    $this->getSession()->getPage()->fillField('uid', $user);
    $this->assertSession()->assertWaitOnAjaxRequest();
    $edit = [
      'customer_type' => 'existing',
    ];
    $this->submitForm($edit, 'Create');

    $this->getSession()->getPage()->pressButton('add_billing_information');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->buttonExists('hide_profile_form');
    $this->assertRenderedAddress($this->defaultAddress, 'billing_profile[0][profile]');
    $this->getSession()->getPage()->pressButton('hide_profile_form');
    $this->assertSession()->assertWaitOnAjaxRequest();
    // Test that commerce_order_test_field_widget_form_alter() has the expected
    // outcome.
    $this->assertSame([], \Drupal::state()->get("commerce_order_test_field_widget_form_alter"));

    // Test creating order items.
    $page = $this->getSession()->getPage();

    $page->pressButton('Add new order item');
    $this->assertSession()->assertWaitOnAjaxRequest();
    // First item with overriding the price.
    $this->getSession()->getPage()->checkField('Override the unit price');
    $purchased_entity_field = $this->assertSession()->waitForElement('css', '[name="order_items[form][0][purchased_entity][0][target_id]"].ui-autocomplete-input');
    $purchased_entity_field->setValue(substr($this->variation->getSku(), 0, 4));
    $this->getSession()->getDriver()->keyDown($purchased_entity_field->getXpath(), ' ');
    $this->assertSession()->waitOnAutocomplete();
    $this->assertSession()->pageTextContains($this->variation->getSku());
    $this->assertCount(1, $this->getSession()->getPage()->findAll('css', '.ui-autocomplete li'));
    $this->getSession()->getPage()->find('css', '.ui-autocomplete li:first-child a')->click();
    $this->assertSession()->fieldValueEquals('order_items[form][0][purchased_entity][0][target_id]', $this->variation->getSku() . ': ' . $this->variation->label() . ' (' . $this->variation->id() . ')');

    $page->fillField('order_items[form][0][quantity][0][value]', '1');
    $this->getSession()->getPage()->pressButton('Create order item');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContainsOnce('Unit price must be a number.');
    $page->fillField('order_items[form][0][unit_price][0][amount][number]', '9.99');
    $this->getSession()->getPage()->pressButton('Create order item');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('9.99');

    // Second item without overriding the price.
    $entity2 = $this->secondVariation->getSku() . ' (' . $this->secondVariation->id() . ')';
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->getSession()->getPage()->pressButton('Add new order item');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $page->fillField('order_items[form][1][purchased_entity][0][target_id]', $entity2);
    $page->fillField('order_items[form][1][quantity][0][value]', '1');
    $this->getSession()->getPage()->pressButton('Create order item');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('5.55');

    // Test editing an order item.
    $edit_buttons = $this->xpath('//div[@data-drupal-selector="edit-order-items-wrapper"]//input[@value="Edit"]');
    $edit_button = reset($edit_buttons);
    $edit_button->click();
    $this->assertSession()->assertWaitOnAjaxRequest();
    $page->fillField('order_items[form][inline_entity_form][entities][0][form][quantity][0][value]', '3');
    $page->fillField('order_items[form][inline_entity_form][entities][0][form][unit_price][0][amount][number]', '1.11');
    $this->getSession()->getPage()->pressButton('Update order item');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('1.11');

    // There is no adjustment - the order should save successfully.
    $this->submitForm([], 'Save');
    $this->assertSession()->pageTextContains('Draft 1 saved.');
    $order = Order::load(1);
    $this->assertNull($order->getBillingProfile());

    // Use an adjustment that is not locked by default.
    $this->drupalGet($order->toUrl('edit-form'));
    $this->getSession()->getPage()->pressButton('add_billing_information');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $edit = [
      'adjustments[0][type]' => 'fee',
      'adjustments[0][definition][label]' => '',
      'adjustments[0][definition][amount][number]' => '2.00',
    ];
    $this->submitForm($edit, 'Save');
    $this->assertSession()->pageTextContains('The adjustment label field is required.');
    $edit['adjustments[0][definition][label]'] = 'Test fee';
    $this->submitForm($edit, 'Save');
    $this->assertSession()->pageTextContains('Draft 1 saved.');

    $this->drupalGet('/admin/commerce/orders');
    $order_number = $this->getSession()->getPage()->findAll('css', 'tr td.views-field-order-number');
    $this->assertEquals(1, count($order_number));

    $order = $this->reloadEntity($order);
    $this->assertEquals(2, count($order->getItems()));
    $this->assertEquals(new Price('10.88', 'USD'), $order->getTotalPrice());
    $this->assertCount(1, $order->getAdjustments());
    $billing_profile = $order->getBillingProfile();
    $this->assertEquals($this->defaultAddress, array_filter($billing_profile->get('address')->first()->toArray()));
    $this->assertEquals($this->defaultProfile->id(), $billing_profile->getData('address_book_profile_id'));
  }

  /**
   * Tests editing an order.
   */
  public function testEditOrder() {
    $address = [
      'country_code' => 'US',
      'postal_code' => '53177',
      'locality' => 'Milwaukee',
      'address_line1' => 'Pabst Blue Ribbon Dr',
      'administrative_area' => 'WI',
      'given_name' => 'Frederick',
      'family_name' => 'Pabst',
    ];
    $profile = Profile::create([
      'type' => 'customer',
      'uid' => 0,
      'address' => $address,
    ]);
    $profile->save();

    $order_item = OrderItem::create([
      'type' => 'default',
      'unit_price' => [
        'number' => '999',
        'currency_code' => 'USD',
      ],
    ]);
    $order_item->save();

    $order = Order::create([
      'type' => 'default',
      'store_id' => $this->store,
      'order_number' => 1,
      'uid' => $this->adminUser,
      'billing_profile' => $profile,
      'order_items' => [$order_item],
      'state' => 'completed',
    ]);
    $order->save();

    $adjustments = [];
    $adjustments[] = new Adjustment([
      'type' => 'custom',
      'label' => '10% off',
      'amount' => new Price('-1.00', 'USD'),
      'percentage' => '0.1',
    ]);
    $adjustments[] = new Adjustment([
      'type' => 'custom',
      'label' => 'Handling fee',
      'amount' => new Price('10.00', 'USD'),
    ]);
    $order->addAdjustment($adjustments[0]);
    $order->addAdjustment($adjustments[1]);
    $order->save();

    $this->drupalGet($order->toUrl('edit-form'));
    $this->assertSession()->buttonNotExists('hide_profile_form');
    $this->assertSession()->fieldValueEquals('adjustments[0][definition][label]', '10% off');
    $this->assertSession()->fieldValueEquals('adjustments[1][definition][label]', 'Handling fee');
    $this->assertSession()->optionExists('adjustments[2][type]', 'Custom');
    $this->assertSession()->optionNotExists('adjustments[2][type]', 'Test order adjustment type');

    $this->assertRenderedAddress($address, 'billing_profile[0][profile]');
    // Select the default profile instead.
    $this->getSession()->getPage()->fillField('billing_profile[0][profile][select_address]', $this->defaultProfile->id());
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertRenderedAddress($this->defaultAddress, 'billing_profile[0][profile]');
    // Edit the default profile and change the street.
    $this->getSession()->getPage()->pressButton('billing_edit');
    $this->assertSession()->assertWaitOnAjaxRequest();
    foreach ($this->defaultAddress as $property => $value) {
      $prefix = 'billing_profile[0][profile][address][0][address]';
      $this->assertSession()->fieldValueEquals($prefix . '[' . $property . ']', $value);
    }
    // The copy checkbox should be hidden and checked.
    $this->assertSession()->fieldNotExists('billing_profile[0][profile][copy_to_address_book]');
    $this->submitForm([
      'billing_profile[0][profile][address][0][address][address_line1]' => '10 Drupal Ave',
    ], 'Save');

    /** @var \Drupal\profile\Entity\ProfileInterface $profile */
    $profile = $this->reloadEntity($profile);
    $this->defaultProfile = $this->reloadEntity($this->defaultProfile);
    $expected_address = ['address_line1' => '10 Drupal Ave'] + $this->defaultAddress;
    $this->assertEquals($expected_address, array_filter($profile->get('address')->first()->toArray()));
    $this->assertEquals($expected_address, array_filter($this->defaultProfile->get('address')->first()->toArray()));
    $this->assertEquals($this->defaultProfile->id(), $profile->getData('address_book_profile_id'));
  }

  /**
   * Tests editing an order after the customer was deleted.
   */
  public function testEditOrderWithDeletedCustomer() {
    $customer = $this->drupalCreateUser();
    $profile = Profile::create([
      'type' => 'customer',
      'uid' => 0,
      'address' => [
        'country_code' => 'US',
        'postal_code' => '53177',
        'locality' => 'Milwaukee',
        'address_line1' => 'Pabst Blue Ribbon Dr',
        'administrative_area' => 'WI',
        'given_name' => 'Frederick',
        'family_name' => 'Pabst',
      ],
    ]);
    $profile->save();
    $order_item = OrderItem::create([
      'type' => 'default',
      'unit_price' => [
        'number' => '999',
        'currency_code' => 'USD',
      ],
    ]);
    $order_item->save();
    $order = Order::create([
      'type' => 'default',
      'state' => 'completed',
      'uid' => $customer->id(),
      'order_number' => 1,
      'store_id' => $this->store,
      'billing_profile' => $profile,
      'order_items' => [$order_item],
    ]);
    $order->save();
    $customer->delete();

    $this->drupalGet($order->toUrl('edit-form'));
    $this->submitForm([], 'Save');
    $this->assertSession()->pageTextContains('Order 1 saved.');
  }

  /**
   * Tests deleting an order.
   */
  public function testDeleteOrder() {
    $order = $this->createEntity('commerce_order', [
      'type' => 'default',
      'mail' => $this->loggedInUser->getEmail(),
      'order_number' => '11111',
      'uid' => $this->loggedInUser,
      'store_id' => $this->store,
    ]);
    $this->drupalGet($order->toUrl('delete-form'));
    $this->assertSession()->pageTextContains($this->t('Are you sure you want to delete @label?', [
      '@label' => $order->label(),
    ]));
    $this->assertSession()->pageTextContains('This action cannot be undone.');
    $this->submitForm([], $this->t('Delete'));

    $this->container->get('entity_type.manager')->getStorage('commerce_order')->resetCache([$order->id()]);
    $order_exists = (bool) Order::load($order->id());
    $this->assertEmpty($order_exists);
  }

  /**
   * Tests unlocking an order.
   */
  public function testUnlockOrder() {
    $order = $this->createEntity('commerce_order', [
      'type' => 'default',
      'mail' => $this->loggedInUser->getEmail(),
      'uid' => $this->loggedInUser,
      'store_id' => $this->store,
      'order_number' => '11111',
      'locked' => TRUE,
    ]);
    $this->drupalGet($order->toUrl('unlock-form'));
    $this->assertSession()->pageTextContains($this->t('Are you sure you want to unlock @label?', [
      '@label' => $order->label(),
    ]));
    $this->submitForm([], $this->t('Unlock'));
    $this->assertSession()->pageTextContains($this->t('The @label has been unlocked.', [
      '@label' => $order->label(),
    ]));

    $this->container->get('entity_type.manager')->getStorage('commerce_order')->resetCache([$order->id()]);
    $order = Order::load($order->id());
    $this->assertFalse($order->isLocked());
  }

  /**
   * Tests resending the order receipt.
   */
  public function testResendReceipt() {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $this->createEntity('commerce_order', [
      'type' => 'default',
      'mail' => $this->loggedInUser->getEmail(),
      'uid' => $this->loggedInUser,
      'store_id' => $this->store,
      'locked' => TRUE,
    ]);

    // No access until the order has been placed.
    $this->drupalGet($order->toUrl('resend-receipt-form'));
    $this->assertSession()->pageTextContains('Access denied');

    // Placing the order sends the receipt.
    $transition = $order->getState()->getTransitions();
    $order->getState()->applyTransition($transition['place']);
    $order->save();
    $emails = $this->getMails();
    $this->assertEquals(1, count($emails));
    $email = array_pop($emails);
    $this->assertEquals('Order #' . $order->getOrderNumber() . ' confirmed', $email['subject']);

    // Change the order number to differentiate from automatic email.
    $order->setOrderNumber('2018/01');
    $order->save();

    $this->drupalGet($order->toUrl('resend-receipt-form'));
    $this->assertSession()->pageTextContains($this->t('Are you sure you want to resend the receipt for @label?', [
      '@label' => $order->label(),
    ]));
    $this->submitForm([], $this->t('Resend receipt'));

    $emails = $this->getMails();
    $this->assertEquals(2, count($emails));
    $email = array_pop($emails);
    $this->assertEquals('Order #2018/01 confirmed', $email['subject']);
    $this->assertSession()->pageTextContains("Order receipt resent.");
  }

  /**
   * Tests that an admin can view an order's details.
   */
  public function testAdminOrderView() {
    // Start from an order without any order items.
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $this->createEntity('commerce_order', [
      'type' => 'default',
      'store_id' => $this->store->id(),
      'mail' => $this->loggedInUser->getEmail(),
      'state' => 'draft',
      'uid' => $this->loggedInUser,
    ]);

    // First test that the current admin user can see the order.
    $this->drupalGet($order->toUrl()->toString());
    $this->assertSession()->pageTextContains($this->loggedInUser->getEmail());

    // Confirm that the order item table is showing the empty text.
    $this->assertSession()->pageTextContains('There are no order items yet.');
    $this->assertSession()->pageTextNotContains('Subtotal');

    // Confirm that the transition buttons are visible and functional.
    $workflow = $order->getState()->getWorkflow();
    $transitions = $workflow->getAllowedTransitions($order->getState()->getId(), $order);
    foreach ($transitions as $transition) {
      $this->assertSession()->linkExists($transition->getLabel());
    }
    $this->click('a.button#edit-place');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('Are you sure you want to apply this transition?');
    $this->assertSession()->linkExists('Cancel');
    $this->assertSession()->buttonExists('Confirm');
    // Note, there is some odd behavior calling the `press()` method on the
    // button, so after asserting it exists, click via this method.
    $this->click('button:contains("Confirm")');
    $this->assertSession()->linkNotExists('Place order');
    $this->assertSession()->linkNotExists('Cancel order');

    // The order was modified and needs to be reloaded.
    $order = $this->reloadEntity($order);

    // Add an order item, confirm that it is displayed.
    $order_item = $this->createEntity('commerce_order_item', [
      'type' => 'default',
      'unit_price' => [
        'number' => '999',
        'currency_code' => 'USD',
      ],
    ]);
    $order->setItems([$order_item]);
    $order->save();

    $this->drupalGet($order->toUrl()->toString());
    $this->assertSession()->pageTextNotContains('There are no order items yet.');
    $this->assertSession()->pageTextContains('$999.00');
    $this->assertSession()->pageTextContains('Subtotal');

    // Logout and check that anonymous users cannot see the order admin screen.
    $this->drupalLogout();
    $this->drupalGet($order->toUrl()->toString());
    $this->assertSession()->pageTextContains('Access denied');
  }

  /**
   * Tests creating a new customer with same email used by an existing customer.
   */
  public function testCreateOrderNewCustomer() {
    $email = 'guest@example.com';
    $this->createUser([], $this->randomString(), FALSE, ['mail' => $email]);

    $this->drupalGet('/admin/commerce/orders');
    $this->getSession()->getPage()->clickLink('Create a new order');
    $this->getSession()->getPage()->selectFieldOption('customer_type', 'new');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $edit = [
      'customer_type' => 'new',
      'mail' => $email,
    ];
    $this->submitForm($edit, $this->t('Create'));
    $this->assertSession()->pageTextContains('The email address guest@example.com is already taken.');
  }

}
