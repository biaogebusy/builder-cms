<?php

namespace Drupal\Tests\commerce_promotion\Kernel;

use Drupal\commerce_promotion\Entity\Coupon;
use Drupal\Tests\commerce_order\Kernel\OrderKernelTestBase;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_order\Entity\OrderType;
use Drupal\commerce_price\Price;
use Drupal\commerce_promotion\Entity\Promotion;
use Drupal\commerce_promotion\Entity\PromotionInterface;

/**
 * Tests promotion compatibility options.
 *
 * @group commerce
 * @group commerce_promotion
 */
class PromotionCompatibilityTest extends OrderKernelTestBase {

  /**
   * The test order.
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
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('commerce_promotion');
    $this->installEntitySchema('commerce_promotion_coupon');
    $this->installConfig(['commerce_promotion']);
    $this->installSchema('commerce_promotion', ['commerce_promotion_usage']);

    $order_item = OrderItem::create([
      'type' => 'test',
      'quantity' => 1,
      'unit_price' => new Price('12.00', 'USD'),
    ]);
    $order_item->save();

    $order = Order::create([
      'type' => 'default',
      'state' => 'completed',
      'mail' => 'test@example.com',
      'ip_address' => '127.0.0.1',
      'order_number' => '6',
      'store_id' => $this->store,
      'order_items' => [$order_item],
      'total_price' => new Price('100.00', 'USD'),
      'uid' => $this->createUser()->id(),
    ]);
    $order->save();
    $this->order = $this->reloadEntity($order);
  }

  /**
   * Tests the compatibility setting.
   */
  public function testCompatibility() {
    $order_type = OrderType::load('default');

    // Starts now, enabled. No end time.
    $promotion1 = Promotion::create([
      'name' => 'Promotion 1',
      'order_types' => [$order_type],
      'stores' => [$this->store->id()],
      'status' => TRUE,
      'offer' => [
        'target_plugin_id' => 'order_percentage_off',
        'target_plugin_configuration' => [
          'percentage' => '0.10',
        ],
      ],
    ]);
    $this->assertEquals(SAVED_NEW, $promotion1->save());

    $promotion2 = Promotion::create([
      'name' => 'Promotion 2',
      'order_types' => [$order_type],
      'stores' => [$this->store->id()],
      'status' => TRUE,
      'offer' => [
        'target_plugin_id' => 'order_percentage_off',
        'target_plugin_configuration' => [
          'percentage' => '0.10',
        ],
      ],
    ]);
    $this->assertEquals(SAVED_NEW, $promotion2->save());

    $this->assertTrue($promotion1->applies($this->order));
    $this->assertTrue($promotion2->applies($this->order));

    $promotion1->setWeight(-10);
    $promotion1->save();

    $promotion2->setWeight(10);
    $promotion2->setCompatibility(PromotionInterface::COMPATIBLE_NONE);
    $promotion2->save();

    $promotion1->apply($this->order);
    $this->assertFalse($promotion2->applies($this->order));

    $this->container->get('commerce_order.order_refresh')->refresh($this->order);
    $this->assertEquals(1, count($this->order->collectAdjustments()));
  }

  /**
   * Tests the promotion compatibility with sequence.
   *
   * @dataProvider promotionDataParameters
   */
  public function testCompatibilityWithSequence(
    array $compatibilities,
    Price $expected_order_total,
    bool $enforce_weight_ordering = FALSE,
  ) {
    $this->config('commerce_promotion.settings')
      ->set('enforce_weight_ordering', $enforce_weight_ordering)
      ->save();

    // Create promotions and coupons.
    $coupon_promotion = Promotion::create([
      'name' => $this->randomString(),
      'order_types' => [$this->order->bundle()],
      'stores' => [$this->store->id()],
      'status' => TRUE,
      'offer' => [
        'target_plugin_id' => 'order_fixed_amount_off',
        'target_plugin_configuration' => [
          'amount' => [
            'number' => '3.00',
            'currency_code' => 'USD',
          ],
        ],
      ],
      'weight' => 1,
    ]);
    $coupon_promotion->setCompatibility($compatibilities[0] ?? PromotionInterface::COMPATIBLE_ANY);
    $coupon_promotion->save();
    $promotion = Promotion::create([
      'name' => $this->randomString(),
      'order_types' => [$this->order->bundle()],
      'stores' => [$this->store->id()],
      'status' => TRUE,
      'offer' => [
        'target_plugin_id' => 'order_fixed_amount_off',
        'target_plugin_configuration' => [
          'amount' => [
            'number' => '2.00',
            'currency_code' => 'USD',
          ],
        ],
      ],
    ]);
    $promotion->setCompatibility($compatibilities[1] ?? PromotionInterface::COMPATIBLE_ANY);
    $promotion->save();
    $coupon = Coupon::create([
      'code' => 'SAVE_3_USD',
      'promotion_id' => $coupon_promotion->id(),
      'status' => TRUE,
    ]);
    $coupon->save();
    $this->order->get('coupons')->appendItem($coupon);
    $this->order->save();
    $this->assertTrue($this->order->getTotalPrice()->equals(new Price('12.00', 'USD')));
    $this->container->get('commerce_order.order_refresh')->refresh($this->order);
    $this->order->recalculateTotalPrice();
    $this->assertTrue($this->order->getTotalPrice()->equals($expected_order_total));
  }

  /**
   * Data provider for ::testCompatibilityWithSequence.
   *
   * @return \Generator
   *   The test data.
   */
  public static function promotionDataParameters(): \Generator {
    // The coupon is applied first (current behavior even if the parent
    // promotion has a higher weight.
    yield [
      [NULL, PromotionInterface::COMPATIBLE_NONE],
      new Price('9.00', 'USD'),
    ];
    // The coupon-less promotion applies first due to the weight and isn't
    // compatible with others, so only this one should apply.
    yield [
      [NULL, PromotionInterface::COMPATIBLE_NONE],
      new Price('10.00', 'USD'),
      TRUE,
    ];
    yield [
      [PromotionInterface::COMPATIBLE_NONE, NULL],
      new Price('9.00', 'USD'),
    ];
    yield [
      [PromotionInterface::COMPATIBLE_NONE, NULL],
      new Price('10.00', 'USD'),
      TRUE,
    ];
  }

}
