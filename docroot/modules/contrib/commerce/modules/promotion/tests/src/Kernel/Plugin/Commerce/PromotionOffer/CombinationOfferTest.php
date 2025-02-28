<?php

namespace Drupal\Tests\commerce_promotion\Kernel\Plugin\Commerce\PromotionOffer;

use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_price\Price;
use Drupal\commerce_product\Entity\Product;
use Drupal\commerce_product\Entity\ProductType;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\commerce_promotion\Entity\Promotion;
use Drupal\commerce_promotion\Plugin\Commerce\PromotionOffer\CombinationOfferInterface;
use Drupal\Tests\commerce_order\Kernel\OrderKernelTestBase;

/**
 * Tests the combination offer plugin.
 *
 * @coversDefaultClass \Drupal\commerce_promotion\Plugin\Commerce\PromotionOffer\CombinationOffer
 *
 * @group commerce
 */
class CombinationOfferTest extends OrderKernelTestBase {

  /**
   * The test order.
   *
   * @var \Drupal\commerce_order\Entity\OrderInterface
   */
  protected $order;

  /**
   * The test variation.
   *
   * @var \Drupal\commerce_product\Entity\ProductVariationInterface
   */
  protected $variation;

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
    $this->installConfig(['commerce_promotion']);
    $this->installSchema('commerce_promotion', ['commerce_promotion_usage']);

    $product_type = ProductType::create([
      'id' => 'test',
      'label' => 'Test',
      'variationType' => 'default',
    ]);
    $product_type->save();

    $variation = ProductVariation::create([
      'type' => 'default',
      'sku' => strtolower($this->randomMachineName()),
      'price' => [
        'number' => '10.00',
        'currency_code' => 'USD',
      ],
    ]);
    $variation->save();
    $this->variation = $variation;

    $product = Product::create([
      'type' => 'default',
      'title' => 'My product',
      'variations' => [$variation],
    ]);
    $product->save();

    $this->order = Order::create([
      'type' => 'default',
      'state' => 'completed',
      'mail' => 'test@example.com',
      'ip_address' => '127.0.0.1',
      'order_number' => '6',
      'uid' => $this->createUser(),
      'store_id' => $this->store,
      'order_items' => [],
    ]);
  }

  /**
   * Tests the combination offer.
   *
   * @covers ::apply
   */
  public function testApply() {
    // Starts now, enabled. No end time.
    $promotion = Promotion::create([
      'name' => 'Promotion 1',
      'order_types' => [$this->order->bundle()],
      'stores' => [$this->store->id()],
      'offer' => [
        'target_plugin_id' => 'combination_offer',
        'target_plugin_configuration' => [
          'offers' => [
            [
              'target_plugin_id' => 'order_item_percentage_off',
              'target_plugin_configuration' => [
                'display_inclusive' => TRUE,
                'percentage' => '0.50',
                'conditions' => [
                  [
                    'plugin' => 'order_item_product_type',
                    'configuration' => [
                      'product_types' => ['default'],
                    ],
                  ],
                ],
              ],
            ],
            [
              'target_plugin_id' => 'order_buy_x_get_y',
              'target_plugin_configuration' => [
                'buy_quantity' => 1,
                'get_quantity' => 1,
                'get_conditions' => [
                  [
                    'plugin' => 'order_item_product_type',
                    'configuration' => [
                      'product_types' => ['test'],
                    ],
                  ],
                ],
                'offer_type' => 'fixed_amount',
                'offer_amount' => [
                  'number' => '2.00',
                  'currency_code' => 'USD',
                ],
              ],
            ],
          ],
        ],
      ],
      'status' => TRUE,
    ]);
    $promotion->save();
    $this->assertInstanceOf(CombinationOfferInterface::class, $promotion->getOffer());
    $this->assertCount(2, $promotion->getOffer()->getOffers());

    $order_item = OrderItem::create([
      'type' => 'default',
      'quantity' => '2',
      'unit_price' => $this->variation->getPrice(),
      'purchased_entity' => $this->variation->id(),
    ]);
    $order_item->save();
    $this->order->addItem($order_item);
    $this->order->save();
    $this->container->get('commerce_order.order_refresh')->refresh($this->order);
    $order_item = $this->order->getItems()[0];

    $this->assertEquals(1, count($order_item->getAdjustments()));
    $adjustment = $order_item->getAdjustments()[0];
    $this->assertEquals(new Price('5', 'USD'), $order_item->getUnitPrice());
    $this->assertEquals(new Price('10', 'USD'), $order_item->getTotalPrice());
    $this->assertEquals(new Price('10', 'USD'), $order_item->getAdjustedTotalPrice());
    $this->assertEquals('Discount', $adjustment->getLabel());
    $this->assertEquals(new Price('-10.00', 'USD'), $adjustment->getAmount());
    $this->assertTrue($adjustment->isIncluded());

    // Add another product to test that the "buy x get y" offer applies.
    $variation = ProductVariation::create([
      'type' => 'default',
      'sku' => $this->randomMachineName(),
      'price' => [
        'number' => '10',
        'currency_code' => 'USD',
      ],
    ]);
    $test_product = Product::create([
      'type' => 'test',
      'title' => $this->randomMachineName(),
      'stores' => [$this->store],
      'variations' => [$variation],
    ]);
    $test_product->save();
    $order_item_storage = \Drupal::entityTypeManager()->getStorage('commerce_order_item');
    $test_order_item = $order_item_storage->createFromPurchasableEntity($variation, [
      'quantity' => '1',
    ]);
    $test_order_item->save();
    $this->order->addItem($test_order_item);
    $this->order->save();
    $this->container->get('commerce_order.order_refresh')->refresh($this->order);

    $order_item = $this->order->getItems()[0];
    $this->assertEquals(1, count($order_item->getAdjustments()));
    $adjustment = $order_item->getAdjustments()[0];
    $this->assertEquals(new Price('5', 'USD'), $order_item->getUnitPrice());
    $this->assertEquals(new Price('10', 'USD'), $order_item->getTotalPrice());
    $this->assertEquals(new Price('10', 'USD'), $order_item->getAdjustedTotalPrice());
    $this->assertEquals('Discount', $adjustment->getLabel());
    $this->assertEquals(new Price('-10.00', 'USD'), $adjustment->getAmount());
    $this->assertTrue($adjustment->isIncluded());

    $test_order_item = $this->order->getItems()[1];
    $this->assertEquals(1, count($test_order_item->getAdjustments()));
    $adjustment = $test_order_item->getAdjustments()[0];
    $this->assertEquals(new Price('10', 'USD'), $test_order_item->getUnitPrice());
    $this->assertEquals(new Price('10', 'USD'), $test_order_item->getTotalPrice());
    $this->assertEquals(new Price('8', 'USD'), $test_order_item->getAdjustedTotalPrice());
    $this->assertFalse($adjustment->isIncluded());
  }

}
