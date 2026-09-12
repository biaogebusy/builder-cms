<?php

namespace Drupal\Tests\commerce_promotion\FunctionalJavascript;

use Drupal\Tests\commerce_order\FunctionalJavascript\OrderWebDriverTestBase;

/**
 * Tests the coupons management on order view page.
 *
 * @group commerce
 */
class OrderManageCouponsTest extends OrderWebDriverTestBase {

  /**
   * A test order.
   *
   * @var \Drupal\commerce_order\Entity\OrderInterface
   */
  protected $order;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'commerce_promotion',
  ];

  /**
   * {@inheritdoc}
   */
  protected function getAdministratorPermissions() {
    return array_merge([
      'administer commerce_order',
      'administer commerce_promotion',
    ], parent::getAdministratorPermissions());
  }

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $order_item = $this->createEntity('commerce_order_item', [
      'type' => 'default',
      'unit_price' => [
        'number' => '9.99',
        'currency_code' => 'USD',
      ],
    ]);
    $this->order = $this->createEntity('commerce_order', [
      'type' => 'default',
      'mail' => $this->loggedInUser->getEmail(),
      'uid' => $this->loggedInUser->id(),
      'order_items' => [$order_item],
      'store_id' => $this->store,
    ]);
    $this->order->save();

    $promotions = [
      [
        'label' => 'Promotion (Disallow multiple coupons)',
        'allow_multiple_coupons' => FALSE,
        'coupons' => [
          'SINGLE_COUPON_FIRST',
          'SINGLE_COUPON_SECOND',
        ],
      ],
      [
        'label' => 'Promotion (Allow multiple coupons)',
        'allow_multiple_coupons' => TRUE,
        'coupons' => [
          'MULTIPLE_COUPON_FIRST',
          'MULTIPLE_COUPON_SECOND',
        ],
      ],
    ];
    foreach ($promotions as $delta => $promotion) {
      /** @var \Drupal\commerce_promotion\Entity\PromotionInterface $promotion_entity */
      $promotion_entity = $this->createEntity('commerce_promotion', [
        'name' => $promotion['label'],
        'order_types' => ['default'],
        'stores' => [$this->store->id()],
        'status' => TRUE,
        'require_coupon' => TRUE,
        'allow_multiple_coupons' => $promotion['allow_multiple_coupons'],
        'offer' => [
          'target_plugin_id' => 'order_percentage_off',
          'target_plugin_configuration' => [
            'percentage' => '0.10',
          ],
        ],
        'start_date' => '2025-01-01',
        'conditions' => [],
        'weight' => $delta,
      ]);
      foreach ($promotion['coupons'] as $coupon_code) {
        /** @var \Drupal\commerce_promotion\Entity\CouponInterface $coupon */
        $coupon = $this->createEntity('commerce_promotion_coupon', [
          'code' => $coupon_code,
          'status' => TRUE,
        ]);
        $coupon->save();
        $promotion_entity->addCoupon($coupon);
      }
      $promotion_entity->save();
    }
  }

  /**
   * Tests applying single coupon on order admin page.
   */
  public function testApplySingleCouponInModal(): void {
    // Confirm that "Manage coupons" link exists and it opens the modal form.
    $this->drupalGet($this->order->toUrl()->toString());
    $this->assertSession()->linkExists('Manage coupons');
    $this->getSession()->getPage()->clickLink('Manage coupons');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->elementTextContains('css', '.ui-dialog .ui-dialog-title', 'Manage coupons');

    // Confirm that modal form have all items.
    $this->assertSession()->buttonExists('Apply coupon');
    $this->assertSession()->buttonExists('Save');
    $this->assertSession()->buttonExists('Cancel');
    $this->assertSession()->buttonNotExists('Remove coupon');
    $this->assertSession()->elementTextNotContains('css', '.ui-dialog .ui-dialog-content', 'Applied coupons');

    // Add coupon code and check the order total is updated.
    $this->getSession()->getPage()->fillField('coupon', 'SINGLE_COUPON_FIRST');
    $this->assertSession()->waitOnAutocomplete();
    $this->assertCount(1, $this->getSession()->getPage()->findAll('css', '.ui-autocomplete li'));
    $this->getSession()->getPage()->find('css', '.ui-autocomplete li:first-child a')->click();
    $this->getSession()->getPage()->pressButton('Apply coupon');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('Coupon successfully redeemed!');
    $this->assertSession()->elementTextContains('css', '.ui-dialog .ui-dialog-content', 'Applied coupons');
    $this->assertSession()->elementTextContains('css', '.ui-dialog .ui-dialog-content', 'SINGLE_COUPON_FIRST');
    $this->assertSession()->buttonExists('Remove coupon');

    // Try to add second coupon and check validation error.
    $this->getSession()->getPage()->fillField('coupon', 'SINGLE_COUPON_SECOND');
    $this->assertSession()->waitOnAutocomplete();
    $this->assertCount(1, $this->getSession()->getPage()->findAll('css', '.ui-autocomplete li'));
    $this->getSession()->getPage()->find('css', '.ui-autocomplete li:first-child a')->click();
    $this->getSession()->getPage()->pressButton('Apply coupon');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('The provided coupon code cannot be applied to your order.');
    $this->assertSession()->elementTextNotContains('css', '.ui-dialog .ui-dialog-content', 'SINGLE_COUPON_SECOND');

    // Clear the "coupon" field value to pass validation.
    $this->getSession()->getPage()->fillField('coupon', '');
    $this->assertEmpty($this->getSession()->getPage()->findField('coupon')->getValue());

    // Save changes and confirm that coupon is applied.
    $this->getSession()->getPage()->find('css', '.ui-dialog .ui-dialog-buttonpane')->pressButton('Save');
    $this->drupalGet($this->order->toUrl());
    $this->assertSession()->pageTextContains('Subtotal $9.99');
    $this->assertSession()->pageTextContains('Discount -$1.00');
    $this->assertSession()->pageTextContains('Total $8.99');
  }

  /**
   * Tests applying multiple coupons on order admin page.
   */
  public function testApplyMultipleCouponsInModal(): void {
    // Open modal form to apply coupons
    $this->drupalGet($this->order->toUrl()->toString());
    $this->getSession()->getPage()->clickLink('Manage coupons');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->elementTextContains('css', '.ui-dialog .ui-dialog-title', 'Manage coupons');

    // Add coupon code and check the order total is updated.
    $this->getSession()->getPage()->fillField('coupon', 'MULTIPLE_COUPON_FIRST');
    $this->assertSession()->waitOnAutocomplete();
    $this->assertCount(1, $this->getSession()->getPage()->findAll('css', '.ui-autocomplete li'));
    $this->getSession()->getPage()->find('css', '.ui-autocomplete li:first-child a')->click();
    $this->getSession()->getPage()->pressButton('Apply coupon');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('Coupon successfully redeemed!');
    $this->assertSession()->elementTextContains('css', '.ui-dialog .ui-dialog-content', 'MULTIPLE_COUPON_FIRST');

    // Add second coupon code.
    $this->getSession()->getPage()->fillField('coupon', 'MULTIPLE_COUPON_SECOND');
    $this->assertSession()->waitOnAutocomplete();
    $this->assertCount(1, $this->getSession()->getPage()->findAll('css', '.ui-autocomplete li'));
    $this->getSession()->getPage()->find('css', '.ui-autocomplete li:first-child a')->click();
    $this->getSession()->getPage()->pressButton('Apply coupon');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('Coupon successfully redeemed!');
    $this->assertSession()->elementTextContains('css', '.ui-dialog .ui-dialog-content', 'MULTIPLE_COUPON_SECOND');

    // Save changes and confirm that coupons are applied.
    $this->getSession()->getPage()->find('css', '.ui-dialog .ui-dialog-buttonpane')->pressButton('Save');
    $this->drupalGet($this->order->toUrl());
    $this->assertSession()->pageTextContains('Subtotal $9.99');
    $this->assertSession()->pageTextContains('Discount -$2.00');
    $this->assertSession()->pageTextContains('Total $7.99');
  }

  /**
   * Tests coupons removal on order admin page.
   */
  public function testCouponsRemovalInModal(): void {
    // Add coupons to the order and confirm they are applied.
    $coupons = $this->container->get('entity_type.manager')
      ->getStorage('commerce_promotion_coupon')
      ->loadByProperties([
        'code' => ['MULTIPLE_COUPON_FIRST', 'MULTIPLE_COUPON_SECOND'],
      ]);
    foreach ($coupons as $coupon) {
      $this->order->get('coupons')->appendItem($coupon->id());
    }
    $this->order->save();
    $this->drupalGet($this->order->toUrl()->toString());
    $this->assertSession()->pageTextContains('Subtotal $9.99');
    $this->assertSession()->pageTextContains('Discount -$2.00');
    $this->assertSession()->pageTextContains('Total $7.99');

    // Open modal to remove coupons.
    $this->getSession()->getPage()->clickLink('Manage coupons');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->elementTextContains('css', '.ui-dialog .ui-dialog-title', 'Manage coupons');
    $this->assertSession()->elementTextContains('css', '.ui-dialog .ui-dialog-content', 'Applied coupons');
    $this->assertSession()->elementTextContains('css', '.ui-dialog .ui-dialog-content', 'MULTIPLE_COUPON_FIRST');
    $this->assertSession()->elementTextContains('css', '.ui-dialog .ui-dialog-content', 'MULTIPLE_COUPON_SECOND');

    // Remove coupon check the order total is updated.
    $this->assertSession()->buttonExists('remove_coupon_1');
    $this->getSession()->getPage()->pressButton('remove_coupon_1');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->elementTextContains('css', '.ui-dialog .ui-dialog-content', 'MULTIPLE_COUPON_FIRST');
    $this->assertSession()->elementTextNotContains('css', '.ui-dialog .ui-dialog-content', 'MULTIPLE_COUPON_SECOND');

    // Save changes and confirm that coupon is removed.
    $this->getSession()->getPage()->find('css', '.ui-dialog .ui-dialog-buttonpane')->pressButton('Save');
    $this->drupalGet($this->order->toUrl());
    $this->assertSession()->pageTextContains('Subtotal $9.99');
    $this->assertSession()->pageTextContains('Discount -$1.00');
    $this->assertSession()->pageTextContains('Total $8.99');
  }

}
