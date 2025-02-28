<?php

namespace Drupal\Tests\commerce_promotion\Kernel;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_price\Price;
use Drupal\commerce_promotion\Entity\Coupon;
use Drupal\commerce_promotion\Entity\Promotion;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\commerce_order\Kernel\OrderKernelTestBase;
use Drupal\user\UserInterface;

/**
 * Tests the promotion order processor.
 *
 * @group commerce
 */
class PromotionOrderProcessorTest extends OrderKernelTestBase {

  /**
   * The test order.
   *
   * @var \Drupal\commerce_order\Entity\OrderInterface
   */
  protected OrderInterface $order;

  /**
   * A sample user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $user;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'commerce_promotion',
    'language',
    'content_translation',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('commerce_promotion');
    $this->installEntitySchema('commerce_promotion_coupon');
    $this->installConfig(['commerce_promotion']);
    $this->installSchema('commerce_promotion', ['commerce_promotion_usage']);
    ConfigurableLanguage::createFromLangcode('fr')->save();

    $this->user = $this->createUser();

    $this->order = Order::create([
      'type' => 'default',
      'state' => 'completed',
      'mail' => 'test@example.com',
      'ip_address' => '127.0.0.1',
      'order_number' => '6',
      'store_id' => $this->store,
      'uid' => $this->user,
      'order_items' => [],
    ]);
  }

  /**
   * Tests the order amount condition.
   */
  public function testOrderTotal() {
    // Use addOrderItem so the total is calculated.
    $order_item = OrderItem::create([
      'type' => 'test',
      'quantity' => 2,
      'unit_price' => [
        'number' => '20.00',
        'currency_code' => 'USD',
      ],
    ]);
    $order_item->save();
    $this->order->addItem($order_item);
    $this->order->save();

    // Starts now, enabled. No end time.
    $promotion = Promotion::create([
      'name' => 'Promotion 1',
      'order_types' => [$this->order->bundle()],
      'stores' => [$this->store->id()],
      'status' => TRUE,
      'offer' => [
        'target_plugin_id' => 'order_percentage_off',
        'target_plugin_configuration' => [
          'percentage' => '0.10',
        ],
      ],
      'conditions' => [
        [
          'target_plugin_id' => 'order_total_price',
          'target_plugin_configuration' => [
            'amount' => [
              'number' => '20.00',
              'currency_code' => 'USD',
            ],
          ],
        ],
      ],
    ]);
    $promotion->save();

    $this->assertTrue($promotion->applies($this->order));
    $this->container->get('commerce_order.order_refresh')->refresh($this->order);
    $this->order->recalculateTotalPrice();

    $this->assertEquals(1, count($this->order->collectAdjustments()));
    $this->assertEquals(new Price('36.00', 'USD'), $this->order->getTotalPrice());
  }

  /**
   * Tests the coupon based promotion processor.
   */
  public function testCouponPromotion() {
    // Use addOrderItem so the total is calculated.
    $order_item = OrderItem::create([
      'type' => 'test',
      'quantity' => 2,
      'unit_price' => [
        'number' => '20.00',
        'currency_code' => 'USD',
      ],
    ]);
    $order_item->save();
    $this->order->addItem($order_item);
    $this->order->set('state', 'draft');
    $this->order->save();

    // Starts now, enabled. No end time.
    $promotion_with_coupon = Promotion::create([
      'name' => 'Promotion (with coupon)',
      'order_types' => [$this->order->bundle()],
      'stores' => [$this->store->id()],
      'offer' => [
        'target_plugin_id' => 'order_percentage_off',
        'target_plugin_configuration' => [
          'percentage' => '0.10',
        ],
      ],
      'conditions' => [
        [
          'target_plugin_id' => 'order_total_price',
          'target_plugin_configuration' => [
            'amount' => [
              'number' => '20.00',
              'currency_code' => 'USD',
            ],
          ],
        ],
      ],
      'start_date' => '2017-01-01',
      'status' => TRUE,
    ]);
    $promotion_with_coupon->save();

    $coupon = Coupon::create([
      'code' => $this->randomString(),
      'status' => TRUE,
    ]);
    $coupon->save();
    $promotion_with_coupon->get('coupons')->appendItem($coupon);
    $promotion_with_coupon->save();

    $this->container->get('commerce_order.order_refresh')->refresh($this->order);

    $this->assertEquals(0, count($this->order->getAdjustments()));

    $this->order->get('coupons')->appendItem($coupon);
    $this->order->save();
    $this->container->get('commerce_order.order_refresh')->refresh($this->order);
    $this->order->recalculateTotalPrice();

    $this->assertEquals(1, count($this->order->collectAdjustments()));
    $this->assertEquals(new Price('36.00', 'USD'), $this->order->getTotalPrice());

    $coupon->setEnabled(FALSE);
    $coupon->save();
    $this->container->get('commerce_order.order_refresh')->refresh($this->order);
    $this->order->recalculateTotalPrice();

    $this->assertEquals(0, count($this->order->collectAdjustments()));
  }

  /**
   * Tests the order refresh to remove coupons from an order when invalid.
   */
  public function testCouponRemoval() {
    $order_item = OrderItem::create([
      'type' => 'test',
      'quantity' => 1,
      'unit_price' => new Price('12.00', 'USD'),
    ]);
    $order_item->save();
    /** @var \Drupal\commerce_order\Entity\Order $order */
    $order = Order::create([
      'type' => 'default',
      'state' => 'draft',
      'mail' => 'test@example.com',
      'ip_address' => '127.0.0.1',
      'order_number' => '6',
      'store_id' => $this->store,
      'uid' => $this->createUser(),
      'order_items' => [$order_item],
    ]);
    $order->setRefreshState(Order::REFRESH_SKIP);
    $order->save();

    $promotion = Promotion::create([
      'name' => 'Promotion',
      'order_types' => ['default'],
      'stores' => [$this->store->id()],
      'usage_limit' => 1,
      'start_date' => '2017-01-01',
      'status' => TRUE,
      'offer' => [
        'target_plugin_id' => 'order_percentage_off',
        'target_plugin_configuration' => [
          'amount' => '0.10',
        ],
      ],
    ]);
    $promotion->save();

    $coupon = Coupon::create([
      'promotion_id' => $promotion->id(),
      'code' => 'coupon_code',
      'usage_limit' => 1,
      'status' => TRUE,
    ]);
    $coupon->save();

    $order->get('coupons')->appendItem($coupon);

    $this->container->get('commerce_order.order_refresh')->refresh($order);
    $this->assertCount(1, $order->get('coupons')->getValue());

    $coupon->setEnabled(FALSE);
    $coupon->save();

    $this->container->get('commerce_order.order_refresh')->refresh($order);
    $this->assertCount(0, $order->get('coupons')->getValue());

    $coupon->setEnabled(TRUE);
    $coupon->save();
    $order->get('coupons')->appendItem($coupon);

    $this->container->get('commerce_order.order_refresh')->refresh($order);
    $this->assertCount(1, $order->get('coupons')->getValue());

    $coupon->delete();

    $this->container->get('commerce_order.order_refresh')->refresh($order);
    $this->assertCount(0, $order->get('coupons')->getValue());
  }

  /**
   * Tests that promotion adjustments are correctly translated.
   */
  public function testAdjustmentsTranslation() {
    // Use addOrderItem so the total is calculated.
    $order_item = OrderItem::create([
      'type' => 'test',
      'quantity' => 2,
      'unit_price' => [
        'number' => '20.00',
        'currency_code' => 'USD',
      ],
    ]);
    $order_item->save();
    $this->order->setRefreshState(Order::REFRESH_SKIP);
    $this->order->addItem($order_item);
    $this->order->save();

    // Starts now, enabled. No end time.
    $promotion = Promotion::create([
      'name' => 'Promotion 1',
      'display_name' => 'Promotion EN',
      'order_types' => [$this->order->bundle()],
      'stores' => [$this->store->id()],
      'status' => TRUE,
      'offer' => [
        'target_plugin_id' => 'order_percentage_off',
        'target_plugin_configuration' => [
          'percentage' => '0.10',
        ],
      ],
    ]);
    $promotion->save();
    $this->assertTrue($promotion->applies($this->order));
    $this->container->get('commerce_order.order_refresh')->refresh($this->order);

    $adjustments = $this->order->collectAdjustments();
    $this->assertEquals(1, count($adjustments));
    $this->assertEquals('Promotion EN', $adjustments[0]->getLabel());

    $this->container->get('content_translation.manager')
      ->setEnabled('commerce_promotion', 'default', TRUE);
    $this->changeActiveLanguage('fr');
    $promotion->addTranslation('fr', [
      'display_name' => 'Promotion FR',
    ]);
    $promotion->save();
    $this->container->get('commerce_order.order_refresh')->refresh($this->order);
    $adjustments = $this->order->collectAdjustments();
    $this->assertEquals(1, count($adjustments));
    $this->assertEquals('Promotion FR', $adjustments[0]->getLabel());

    // Test that a promotion with coupons is correctly translated as well.
    $coupon = Coupon::create([
      'code' => $this->randomString(),
      'promotion_id' => $promotion->id(),
      'status' => TRUE,
    ]);
    $coupon->save();

    $this->container->get('commerce_order.order_refresh')->refresh($this->order);
    $this->assertEquals(0, count($this->order->collectAdjustments()));

    $this->order->get('coupons')->appendItem($coupon);
    $this->order->save();
    $this->container->get('commerce_order.order_refresh')->refresh($this->order);
    $adjustments = $this->order->collectAdjustments();
    $this->assertEquals(1, count($adjustments));
    $this->assertEquals('Promotion FR', $adjustments[0]->getLabel());

    $this->changeActiveLanguage('en');
    $this->container->get('commerce_order.order_refresh')->refresh($this->order);
    $adjustments = $this->order->collectAdjustments();
    $this->assertEquals(1, count($adjustments));
    $this->assertEquals('Promotion EN', $adjustments[0]->getLabel());
  }

  /**
   * Tests that the promotion order processor cleans up auto added order items.
   */
  public function testOrderItemRemoval() {
    // We simulate an order item automatically added by the BuyXGetY offer.
    $order_item = OrderItem::create([
      'type' => 'test',
      'quantity' => 0,
      'unit_price' => [
        'number' => '20.00',
        'currency_code' => 'USD',
      ],
      'data' => [
        'owned_by_promotion' => TRUE,
      ],
    ]);
    $order_item->save();
    $this->order->setRefreshState(Order::REFRESH_SKIP);
    $this->order->addItem($order_item);
    $this->order->save();

    $this->assertCount(1, $this->order->getItems());
    $this->container->get('commerce_promotion.promotion_order_processor')->process($this->order);
    $this->assertCount(0, $this->order->getItems());
  }

  /**
   * Changes the active language for translations.
   *
   * @param string $langcode
   *   The langcode.
   */
  protected function changeActiveLanguage($langcode) {
    $language = ConfigurableLanguage::createFromLangcode($langcode);
    $this->container->get('language.default')->set($language);
    \Drupal::languageManager()->reset();
  }

}
