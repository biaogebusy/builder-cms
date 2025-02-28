<?php

namespace Drupal\Tests\commerce_promotion\Kernel\Plugin\Commerce\PromotionOffer;

use Drupal\commerce_order\Adjustment;
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_price\Calculator;
use Drupal\commerce_price\Price;
use Drupal\commerce_product\Entity\Product;
use Drupal\commerce_product\Entity\ProductType;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\commerce_promotion\Entity\Promotion;
use Drupal\Tests\commerce_order\Kernel\OrderKernelTestBase;

/**
 * Tests the "Buy X Get Y" offer.
 *
 * @coversDefaultClass \Drupal\commerce_promotion\Plugin\Commerce\PromotionOffer\BuyXGetY
 *
 * @group commerce
 */
class BuyXGetYTest extends OrderKernelTestBase {

  /**
   * The test order.
   *
   * @var \Drupal\commerce_order\Entity\OrderInterface
   */
  protected $order;

  /**
   * The test promotion.
   *
   * @var \Drupal\commerce_promotion\Entity\PromotionInterface
   */
  protected $promotion;

  /**
   * The test variations.
   *
   * @var \Drupal\commerce_product\Entity\ProductVariationInterface[]
   */
  protected $variations = [];

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

    for ($i = 0; $i < 4; $i++) {
      $this->variations[$i] = ProductVariation::create([
        'type' => 'default',
        'sku' => $this->randomMachineName(),
        'price' => [
          'number' => Calculator::multiply('10', $i + 1),
          'currency_code' => 'USD',
        ],
      ]);
      $this->variations[$i]->save();
    }

    $first_product = Product::create([
      'type' => 'test',
      'title' => $this->randomMachineName(),
      'stores' => [$this->store],
      'variations' => [$this->variations[0]],
    ]);
    $first_product->save();
    $second_product = Product::create([
      'type' => 'default',
      'title' => $this->randomMachineName(),
      'stores' => [$this->store],
      'variations' => [$this->variations[1]],
    ]);
    $second_product->save();
    $third_product = Product::create([
      'type' => 'default',
      'title' => 'Hat 1',
      'stores' => [$this->store],
      'variations' => [$this->variations[2]],
    ]);
    $third_product->save();
    $fourth_product = Product::create([
      'type' => 'default',
      'title' => 'Hat 2',
      'stores' => [$this->store],
      'variations' => [$this->variations[3]],
    ]);
    $fourth_product->save();

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

    // Buy 6 "test" products, get 4 hats.
    $promotion = Promotion::create([
      'name' => 'Promotion 1',
      'order_types' => [$this->order->bundle()],
      'stores' => [$this->store->id()],
      'offer' => [
        'target_plugin_id' => 'order_buy_x_get_y',
        'target_plugin_configuration' => [
          'buy_quantity' => 6,
          'buy_conditions' => [
            [
              'plugin' => 'order_item_product_type',
              'configuration' => [
                'product_types' => ['test'],
              ],
            ],
          ],
          'get_quantity' => 4,
          'get_conditions' => [
            [
              'plugin' => 'order_item_product',
              'configuration' => [
                'products' => [
                  ['product' => $third_product->uuid()],
                  ['product' => $fourth_product->uuid()],
                ],
              ],
            ],
          ],
          'offer_type' => 'fixed_amount',
          'offer_amount' => [
            'number' => '1.00',
            'currency_code' => 'USD',
          ],
        ],
      ],
      'status' => TRUE,
    ]);
    $promotion->save();
    $this->promotion = $this->reloadEntity($promotion);
  }

  /**
   * Tests the non-applicable use cases.
   *
   * @covers ::apply
   */
  public function testNotApplicable() {
    /** @var \Drupal\commerce_order\OrderItemStorageInterface $order_item_storage */
    $order_item_storage = \Drupal::entityTypeManager()->getStorage('commerce_order_item');
    $first_order_item = $order_item_storage->createFromPurchasableEntity($this->variations[0], [
      'quantity' => '2',
    ]);
    $first_order_item->save();
    $second_order_item = $order_item_storage->createFromPurchasableEntity($this->variations[1], [
      'quantity' => '4',
    ]);
    $second_order_item->save();
    $this->order->setItems([$first_order_item, $second_order_item]);
    $this->order->save();

    // Insufficient purchase quantity.
    // Only the first order item is counted (due to the product type condition),
    // and its quantity is too small (2 < 6).
    $this->container->get('commerce_order.order_refresh')->refresh($this->order);
    $this->assertEmpty($this->order->collectAdjustments());

    // Sufficient purchase quantity, but no offer order item found.
    $first_order_item->setQuantity(6);
    $first_order_item->save();
    $this->order->save();
    $this->container->get('commerce_order.order_refresh')->refresh($this->order);
    $this->assertEmpty($this->order->collectAdjustments());
  }

  /**
   * Tests the fixed amount off offer type.
   *
   * @covers ::apply
   */
  public function testFixedAmountOff() {
    /** @var \Drupal\commerce_order\OrderItemStorageInterface $order_item_storage */
    $order_item_storage = \Drupal::entityTypeManager()->getStorage('commerce_order_item');
    $first_order_item = $order_item_storage->createFromPurchasableEntity($this->variations[0], [
      'quantity' => '7',
    ]);
    $first_order_item->save();
    $second_order_item = $order_item_storage->createFromPurchasableEntity($this->variations[1], [
      'quantity' => '2',
    ]);
    // Test having a single offer order item, quantity < get_quantity.
    $third_order_item = $order_item_storage->createFromPurchasableEntity($this->variations[2], [
      'quantity' => '3',
    ]);
    $second_order_item->save();
    $this->order->setItems([$first_order_item, $second_order_item, $third_order_item]);
    $this->order->save();
    $this->container->get('commerce_order.order_refresh')->refresh($this->order);
    [$first_order_item, $second_order_item, $third_order_item] = $this->order->getItems();

    $this->assertCount(0, $first_order_item->getAdjustments());
    $this->assertCount(0, $second_order_item->getAdjustments());
    $this->assertCount(1, $third_order_item->getAdjustments());

    $adjustments = $third_order_item->getAdjustments();
    $adjustment = reset($adjustments);
    $this->assertEquals('promotion', $adjustment->getType());
    $this->assertEquals('Discount', $adjustment->getLabel());
    $this->assertEquals(new Price('-3', 'USD'), $adjustment->getAmount());
    $this->assertEquals($this->promotion->id(), $adjustment->getSourceId());

    // Test having two offer order items, one ($third_order_item) reduced
    // completely, the other ($fourth_order_item) reduced partially.
    $fourth_order_item = $order_item_storage->createFromPurchasableEntity($this->variations[2], [
      'quantity' => '2',
    ]);
    $this->order->addItem($fourth_order_item);
    $this->order->save();
    $this->container->get('commerce_order.order_refresh')->refresh($this->order);
    [$first_order_item, $second_order_item, $third_order_item, $fourth_order_item] = $this->order->getItems();

    $this->assertCount(0, $first_order_item->getAdjustments());
    $this->assertCount(0, $second_order_item->getAdjustments());
    $this->assertCount(1, $third_order_item->getAdjustments());
    $this->assertCount(1, $fourth_order_item->getAdjustments());

    $adjustments = $third_order_item->getAdjustments();
    $adjustment = reset($adjustments);
    $this->assertFalse($adjustment->isIncluded());
    $this->assertEquals('promotion', $adjustment->getType());
    $this->assertEquals('Discount', $adjustment->getLabel());
    $this->assertEquals(new Price('-3', 'USD'), $adjustment->getAmount());
    $this->assertEquals($this->promotion->id(), $adjustment->getSourceId());

    $adjustments = $fourth_order_item->getAdjustments();
    $adjustment = reset($adjustments);
    $this->assertFalse($adjustment->isIncluded());
    $this->assertEquals('promotion', $adjustment->getType());
    $this->assertEquals('Discount', $adjustment->getLabel());
    $this->assertEquals(new Price('-1', 'USD'), $adjustment->getAmount());
    $this->assertEquals($this->promotion->id(), $adjustment->getSourceId());

    // Test the inclusive promotion behavior.
    $offer = $this->promotion->getOffer();
    $offer_configuration = $offer->getConfiguration();
    $offer_configuration['display_inclusive'] = TRUE;
    $offer->setConfiguration($offer_configuration);
    $this->promotion->setOffer($offer);
    $this->container->get('commerce_order.order_refresh')->refresh($this->order);

    $this->assertCount(0, $first_order_item->getAdjustments());
    $this->assertCount(0, $second_order_item->getAdjustments());
    $this->assertCount(1, $third_order_item->getAdjustments());

    $adjustments = $third_order_item->getAdjustments();
    $adjustment = reset($adjustments);
    $this->assertEquals('promotion', $adjustment->getType());
    $this->assertTrue($adjustment->isIncluded());
    $this->assertEquals('Discount', $adjustment->getLabel());
    $this->assertEquals(new Price('-3', 'USD'), $adjustment->getAmount());
    $this->assertEquals($this->promotion->id(), $adjustment->getSourceId());
    $this->assertEquals(new Price('29', 'USD'), $third_order_item->getUnitPrice());

    $adjustments = $fourth_order_item->getAdjustments();
    $adjustment = reset($adjustments);
    $this->assertTrue($adjustment->isIncluded());
    $this->assertEquals('promotion', $adjustment->getType());
    $this->assertEquals('Discount', $adjustment->getLabel());
    $this->assertEquals(new Price('-1', 'USD'), $adjustment->getAmount());
    $this->assertEquals($this->promotion->id(), $adjustment->getSourceId());
    $this->assertEquals(new Price('29', 'USD'), $third_order_item->getUnitPrice());
  }

  /**
   * Tests the percentage off offer type.
   *
   * @covers ::apply
   */
  public function testPercentageOff() {
    $offer = $this->promotion->getOffer();
    $offer_configuration = $offer->getConfiguration();
    $offer_configuration['offer_type'] = 'percentage';
    $offer_configuration['offer_percentage'] = '0.1';
    $offer_configuration['offer_amount'] = NULL;
    $offer->setConfiguration($offer_configuration);
    $this->promotion->setOffer($offer);
    $this->promotion->setDisplayName('Buy X Get Y!');

    /** @var \Drupal\commerce_order\OrderItemStorageInterface $order_item_storage */
    $order_item_storage = \Drupal::entityTypeManager()->getStorage('commerce_order_item');
    $first_order_item = $order_item_storage->createFromPurchasableEntity($this->variations[0], [
      // Double the buy_quantity -> double the get_quantity.
      'quantity' => '13',
    ]);
    $first_order_item->save();
    $second_order_item = $order_item_storage->createFromPurchasableEntity($this->variations[1], [
      'quantity' => '2',
    ]);
    // Test having a single offer order item, quantity < get_quantity.
    $third_order_item = $order_item_storage->createFromPurchasableEntity($this->variations[2], [
      'quantity' => '6',
    ]);
    $second_order_item->save();
    $this->order->setItems([$first_order_item, $second_order_item, $third_order_item]);
    $this->order->save();
    $this->container->get('commerce_order.order_refresh')->refresh($this->order);
    [$first_order_item, $second_order_item, $third_order_item] = $this->order->getItems();

    $this->assertCount(0, $first_order_item->getAdjustments());
    $this->assertCount(0, $second_order_item->getAdjustments());
    $this->assertCount(1, $third_order_item->getAdjustments());

    $adjustments = $third_order_item->getAdjustments();
    $adjustment = reset($adjustments);
    $this->assertEquals('promotion', $adjustment->getType());
    $this->assertEquals('Buy X Get Y!', $adjustment->getLabel());
    $this->assertEquals(new Price('-18', 'USD'), $adjustment->getAmount());
    $this->assertEquals($this->promotion->id(), $adjustment->getSourceId());

    // Test having two offer order items, one ($third_order_item) reduced
    // completely, the other ($fourth_order_item) reduced partially.
    $fourth_order_item = $order_item_storage->createFromPurchasableEntity($this->variations[2], [
      'quantity' => '3',
    ]);

    $this->order->addItem($fourth_order_item);
    $this->order->save();
    $this->container->get('commerce_order.order_refresh')->refresh($this->order);
    [$first_order_item, $second_order_item, $third_order_item, $fourth_order_item] = $this->order->getItems();

    $this->assertCount(0, $first_order_item->getAdjustments());
    $this->assertCount(0, $second_order_item->getAdjustments());
    $this->assertCount(1, $third_order_item->getAdjustments());
    $this->assertCount(1, $fourth_order_item->getAdjustments());

    $adjustments = $third_order_item->getAdjustments();
    $adjustment = reset($adjustments);
    $this->assertEquals('promotion', $adjustment->getType());
    $this->assertFalse($adjustment->isIncluded());
    $this->assertEquals('Buy X Get Y!', $adjustment->getLabel());
    $this->assertEquals(new Price('-18', 'USD'), $adjustment->getAmount());
    $this->assertEquals($this->promotion->id(), $adjustment->getSourceId());

    $adjustments = $fourth_order_item->getAdjustments();
    $adjustment = reset($adjustments);
    $this->assertFalse($adjustment->isIncluded());
    $this->assertEquals('promotion', $adjustment->getType());
    $this->assertEquals('Buy X Get Y!', $adjustment->getLabel());
    $this->assertEquals(new Price('-6', 'USD'), $adjustment->getAmount());
    $this->assertEquals($this->promotion->id(), $adjustment->getSourceId());

    // Test the inclusive promotion behavior.
    $offer = $this->promotion->getOffer();
    $offer_configuration = $offer->getConfiguration();
    $offer_configuration['display_inclusive'] = TRUE;
    $offer->setConfiguration($offer_configuration);
    $this->promotion->setOffer($offer);
    $this->container->get('commerce_order.order_refresh')->refresh($this->order);
    $this->assertCount(0, $first_order_item->getAdjustments());
    $this->assertCount(0, $second_order_item->getAdjustments());
    $this->assertCount(1, $third_order_item->getAdjustments());
    $this->assertCount(1, $fourth_order_item->getAdjustments());

    $adjustments = $third_order_item->getAdjustments();
    $adjustment = reset($adjustments);
    $this->assertEquals('promotion', $adjustment->getType());
    $this->assertEquals('Buy X Get Y!', $adjustment->getLabel());
    $this->assertTrue($adjustment->isIncluded());
    $this->assertEquals(new Price('-18', 'USD'), $adjustment->getAmount());
    $this->assertEquals(new Price('27', 'USD'), $third_order_item->getUnitPrice());
    $this->assertEquals($this->promotion->id(), $adjustment->getSourceId());

    $adjustments = $fourth_order_item->getAdjustments();
    $adjustment = reset($adjustments);
    $this->assertTrue($adjustment->isIncluded());
    $this->assertEquals('promotion', $adjustment->getType());
    $this->assertEquals('Buy X Get Y!', $adjustment->getLabel());
    $this->assertEquals(new Price('-6', 'USD'), $adjustment->getAmount());
    $this->assertEquals(new Price('28', 'USD'), $fourth_order_item->getUnitPrice());
    $this->assertEquals($this->promotion->id(), $adjustment->getSourceId());
  }

  /**
   * Tests the same order item matching both buy and get conditions.
   *
   * @covers ::apply
   */
  public function testSameOrderItem() {
    $offer = $this->promotion->getOffer();
    $offer_configuration = $offer->getConfiguration();
    $offer_configuration['buy_quantity'] = '1';
    $offer_configuration['buy_conditions'] = [];
    $offer_configuration['get_quantity'] = '1';
    $offer_configuration['get_conditions'] = [];
    $offer->setConfiguration($offer_configuration);
    $this->promotion->setOffer($offer);

    /** @var \Drupal\commerce_order\OrderItemStorageInterface $order_item_storage */
    $order_item_storage = \Drupal::entityTypeManager()->getStorage('commerce_order_item');
    // '2' buy quantities, '2' get quantities, '1' ignored/irrelevant quantity.
    $order_item = $order_item_storage->createFromPurchasableEntity($this->variations[0], [
      'quantity' => '5',
    ]);
    $order_item->save();
    $this->order->addItem($order_item);
    $this->order->save();
    $this->container->get('commerce_order.order_refresh')->refresh($this->order);
    [$order_item] = $this->order->getItems();

    $this->assertCount(1, $order_item->getAdjustments());
    $adjustments = $order_item->getAdjustments();
    $adjustment = reset($adjustments);
    $this->assertEquals('promotion', $adjustment->getType());
    $this->assertEquals(new Price('-2', 'USD'), $adjustment->getAmount());
    $this->assertEquals($this->promotion->id(), $adjustment->getSourceId());
  }

  /**
   * Tests order item sorting.
   *
   * @covers ::apply
   */
  public function testOrderItemSorting() {
    // First cheapest product gets 50% off.
    $offer = $this->promotion->getOffer();
    $offer_configuration = $offer->getConfiguration();
    $offer_configuration['get_quantity'] = '1';
    $offer_configuration['offer_type'] = 'percentage';
    $offer_configuration['offer_percentage'] = '0.5';
    $offer_configuration['offer_amount'] = NULL;
    $offer->setConfiguration($offer_configuration);
    $this->promotion->setOffer($offer);

    /** @var \Drupal\commerce_order\OrderItemStorageInterface $order_item_storage */
    $order_item_storage = \Drupal::entityTypeManager()->getStorage('commerce_order_item');
    $first_order_item = $order_item_storage->createFromPurchasableEntity($this->variations[0], [
      'quantity' => '6',
    ]);
    $first_order_item->save();
    // Both order items match the get_conditions, $third_order_item should be
    // discounted because it is cheaper.
    $second_order_item = $order_item_storage->createFromPurchasableEntity($this->variations[3], [
      'quantity' => '1',
    ]);
    $second_order_item->save();
    $third_order_item = $order_item_storage->createFromPurchasableEntity($this->variations[2], [
      'quantity' => '1',
    ]);
    $third_order_item->save();
    $this->order->setItems([$first_order_item, $second_order_item, $third_order_item]);
    $this->order->save();
    $this->container->get('commerce_order.order_refresh')->refresh($this->order);
    [$first_order_item, $second_order_item, $third_order_item] = $this->order->getItems();

    $this->assertCount(0, $first_order_item->getAdjustments());
    $this->assertCount(0, $second_order_item->getAdjustments());
    $this->assertCount(1, $third_order_item->getAdjustments());
  }

  /**
   * Tests order item sorting when a 'get_condition' product has a higher value.
   *
   * @covers ::apply
   */
  public function testOrderItemSortingWithHigherValueGetCondition() {
    $offer = $this->promotion->getOffer();
    $offer_configuration = $offer->getConfiguration();
    // The customer purchases 2 quantities of any product.
    $offer_configuration['buy_quantity'] = '2';
    $offer_configuration['buy_conditions'] = [];
    // The customer receives 1 specific product for free.
    $offer_configuration['get_quantity'] = '1';
    $offer_configuration['get_conditions'] = [
      [
        'plugin' => 'order_item_purchased_entity:commerce_product_variation',
        'configuration' => [
          'entities' => [
            $this->variations[2]->uuid(),
          ],
        ],
      ],
    ];
    $offer_configuration['offer_type'] = 'percentage';
    $offer_configuration['offer_percentage'] = '1';
    $offer_configuration['offer_amount'] = NULL;
    $offer->setConfiguration($offer_configuration);
    $this->promotion->setOffer($offer);

    /** @var \Drupal\commerce_order\OrderItemStorageInterface $order_item_storage */
    $order_item_storage = \Drupal::entityTypeManager()->getStorage('commerce_order_item');
    // Price of first order item: 10. Matches the first required quantity of the
    // buy condition.
    $first_order_item = $order_item_storage->createFromPurchasableEntity($this->variations[0], [
      'quantity' => '1',
    ]);
    $first_order_item->save();
    // Price of second order item: 20. Matches the second required quantity of
    // the buy condition.
    $second_order_item = $order_item_storage->createFromPurchasableEntity($this->variations[1], [
      'quantity' => '1',
    ]);
    $second_order_item->save();
    // Price of third order item: 30. Matches the get_conditions, which means
    // that this order item will be discounted 100%. The purpose of this test
    // is to check the case when the get_conditions product has an equal or
    // higher value than the order items that match the buy_conditions.
    $third_order_item = $order_item_storage->createFromPurchasableEntity($this->variations[2], [
      'quantity' => '1',
    ]);
    $third_order_item->save();
    $this->order->setItems([$first_order_item, $second_order_item, $third_order_item]);
    $this->order->save();
    $this->container->get('commerce_order.order_refresh')->refresh($this->order);
    [$first_order_item, $second_order_item, $third_order_item] = $this->order->getItems();

    $this->assertCount(0, $first_order_item->getAdjustments());
    $this->assertCount(0, $second_order_item->getAdjustments());
    $this->assertCount(1, $third_order_item->getAdjustments());
    $adjustments = $third_order_item->getAdjustments();
    $adjustment = reset($adjustments);
    $this->assertEquals('promotion', $adjustment->getType());
    $this->assertEquals(new Price('-30', 'USD'), $adjustment->getAmount());
    $this->assertEquals($this->promotion->id(), $adjustment->getSourceId());
  }

  /**
   * Tests working with decimal quantities.
   *
   * @covers ::apply
   */
  public function testDecimalQuantities() {
    /** @var \Drupal\commerce_order\OrderItemStorageInterface $order_item_storage */
    $order_item_storage = \Drupal::entityTypeManager()->getStorage('commerce_order_item');
    $first_order_item = $order_item_storage->createFromPurchasableEntity($this->variations[0], [
      'quantity' => '2.5',
    ]);
    $first_order_item->save();
    $second_order_item = $order_item_storage->createFromPurchasableEntity($this->variations[0], [
      'quantity' => '3.5',
    ]);
    $second_order_item->save();
    $third_order_item = $order_item_storage->createFromPurchasableEntity($this->variations[2], [
      'quantity' => '1.5',
    ]);
    $third_order_item->save();
    $fourth_order_item = $order_item_storage->createFromPurchasableEntity($this->variations[2], [
      'quantity' => '5.5',
    ]);
    $fourth_order_item->save();
    $this->order->setItems([$first_order_item, $second_order_item, $third_order_item, $fourth_order_item]);
    $this->order->save();
    $this->container->get('commerce_order.order_refresh')->refresh($this->order);
    [$first_order_item, $second_order_item, $third_order_item, $fourth_order_item] = $this->order->getItems();

    $this->assertCount(0, $first_order_item->getAdjustments());
    $this->assertCount(0, $second_order_item->getAdjustments());
    $this->assertCount(1, $third_order_item->getAdjustments());
    $this->assertCount(1, $fourth_order_item->getAdjustments());

    $adjustments = $third_order_item->getAdjustments();
    $adjustment = reset($adjustments);
    $this->assertEquals('promotion', $adjustment->getType());
    $this->assertEquals(new Price('-1.5', 'USD'), $adjustment->getAmount());
    $this->assertEquals($this->promotion->id(), $adjustment->getSourceId());

    $adjustments = $fourth_order_item->getAdjustments();
    $adjustment = reset($adjustments);
    $this->assertEquals('promotion', $adjustment->getType());
    $this->assertEquals(new Price('-2.5', 'USD'), $adjustment->getAmount());
    $this->assertEquals($this->promotion->id(), $adjustment->getSourceId());
  }

  /**
   * Tests the 'auto-add' offered item capability.
   *
   * @covers ::apply
   */
  public function testAutoAddOrderItem() {
    // Configure a "buy 3 of anything, get 1 specific product free" offer.
    $offer = $this->promotion->getOffer();
    $offer_configuration = $offer->getConfiguration();
    // The customer purchases 3 quantities of any product.
    $offer_configuration['buy_quantity'] = '3';
    $offer_configuration['buy_conditions'] = [];
    // The customer receives 1 specific product for free.
    $offer_configuration['get_quantity'] = '1';
    $offer_configuration['get_conditions'] = [
      [
        'plugin' => 'order_item_purchased_entity:commerce_product_variation',
        'configuration' => [
          'entities' => [
            $this->variations[2]->uuid(),
          ],
        ],
      ],
    ];
    $offer_configuration['get_auto_add'] = TRUE;
    $offer_configuration['offer_type'] = 'percentage';
    $offer_configuration['offer_percentage'] = '1';
    $offer_configuration['offer_amount'] = NULL;
    $offer->setConfiguration($offer_configuration);
    $this->promotion->setOffer($offer);

    /** @var \Drupal\commerce_order\OrderItemStorageInterface $order_item_storage */
    $order_item_storage = \Drupal::entityTypeManager()->getStorage('commerce_order_item');
    // We add the same purchasable entity as the auto added one to test the
    // trickier case.
    $first_order_item = $order_item_storage->createFromPurchasableEntity($this->variations[2], [
      'quantity' => '3',
    ]);
    $this->order->setItems([$first_order_item]);
    $this->order->save();
    $this->container->get('commerce_order.order_refresh')->refresh($this->order);

    $order_items = $this->order->getItems();
    $this->assertCount(2, $order_items);
    // The offer automatically added a second order item.
    [$first_order_item, $second_order_item] = $order_items;

    // Store the second order item ID to ensure it doesn't change after each
    // refresh.
    $second_order_item_id = $second_order_item->id();
    $this->assertCount(0, $first_order_item->getAdjustments());
    $this->assertCount(1, $second_order_item->getAdjustments());
    $this->assertEquals(1, $second_order_item->getQuantity());
    $this->assertEquals($this->variations[2]->id(), $second_order_item->getPurchasedEntityId());
    $this->assertTrue($second_order_item->getData('owned_by_promotion'));
    $this->assertAdjustmentPrice($second_order_item->getAdjustments()[0], '-30');

    // Increase the quantity of the "buy" product to 4, the quantity of the
    // offered product will not change.
    $first_order_item->setQuantity(4);
    $this->order->setItems([$first_order_item, $second_order_item]);
    $this->order->save();
    $this->container->get('commerce_order.order_refresh')->refresh($this->order);

    [$first_order_item, $second_order_item] = $this->order->getItems();

    $this->assertEquals($second_order_item_id, $second_order_item->id());
    $this->assertCount(0, $first_order_item->getAdjustments());
    $this->assertEquals(4, $first_order_item->getQuantity());
    $this->assertCount(1, $second_order_item->getAdjustments());
    $this->assertEquals(1, $second_order_item->getQuantity());

    // Increase the quantity of the "buy" product to 6, the quantity of the
    // offered product will be increased to 2.
    $first_order_item->setQuantity(6);
    $this->order->setItems([$first_order_item, $second_order_item]);
    $this->order->save();
    $this->container->get('commerce_order.order_refresh')->refresh($this->order);

    [$first_order_item, $second_order_item] = $this->order->getItems();

    $this->assertEquals($second_order_item_id, $second_order_item->id());
    $this->assertEquals(6, $first_order_item->getQuantity());
    $this->assertCount(0, $first_order_item->getAdjustments());
    $this->assertEquals(2, $second_order_item->getQuantity());
    $this->assertCount(1, $second_order_item->getAdjustments());
    $this->assertAdjustmentPrice($second_order_item->getAdjustments()[0], '-60');

    // Try to remove the "get" product from the order, it will be added back
    // automatically.
    $this->order->removeItem($second_order_item);
    $this->assertCount(1, $this->order->getItems());
    $this->order->save();
    $this->container->get('commerce_order.order_refresh')->refresh($this->order);

    [$first_order_item, $second_order_item] = $this->order->getItems();

    $this->assertEquals(6, $first_order_item->getQuantity());
    $this->assertEquals(2, $second_order_item->getQuantity());
    $this->assertCount(1, $second_order_item->getAdjustments());
    $this->assertAdjustmentPrice($second_order_item->getAdjustments()[0], '-60');

    // Decrease the quantity of the "buy" product from the order, the "get"
    // quantity will be decreased and the discount will only be applied once.
    $first_order_item->setQuantity(5);
    $this->order->setItems([$first_order_item, $second_order_item]);
    $this->order->save();
    $this->container->get('commerce_order.order_refresh')->refresh($this->order);

    [$first_order_item, $second_order_item] = $this->order->getItems();

    $this->assertEquals(5, $first_order_item->getQuantity());
    $this->assertEquals(1, $second_order_item->getQuantity());
    $this->assertCount(1, $second_order_item->getAdjustments());
    $this->assertAdjustmentPrice($second_order_item->getAdjustments()[0], '-30');

    // Test that the order item data key holding the auto-added quantity is
    // cleared when the get order item is no longer eligible for the offer, but
    // extra quantity was added by the customer.
    $this->assertNotNull($second_order_item->getData('promotion:1:auto_add_quantity'));
    $second_order_item->setQuantity('2');
    $first_order_item->setQuantity('1');
    $this->container->get('commerce_order.order_refresh')->refresh($this->order);
    [, $second_order_item] = $this->order->getItems();
    $this->assertNull($second_order_item->getData('promotion:1:auto_add_quantity'));
    $this->assertEquals(1, $second_order_item->getQuantity());
  }

  /**
   * Tests the "auto-add" behavior when the get item is already in the order.
   */
  public function testAutoAddWithGetProductInOrder() {
    $offer = $this->promotion->getOffer();
    $offer_configuration = $offer->getConfiguration();
    // The customer purchases 3 quantities of any product.
    $offer_configuration['buy_quantity'] = '3';
    $offer_configuration['buy_conditions'] = [
      [
        'plugin' => 'order_item_purchased_entity:commerce_product_variation',
        'configuration' => [
          'entities' => [
            $this->variations[0]->uuid(),
          ],
        ],
      ],
    ];
    // The customer receives 1 specific product for free.
    $offer_configuration['get_quantity'] = '1';
    $offer_configuration['get_conditions'] = [
      [
        'plugin' => 'order_item_purchased_entity:commerce_product_variation',
        'configuration' => [
          'entities' => [
            $this->variations[2]->uuid(),
          ],
        ],
      ],
    ];
    $offer_configuration['get_auto_add'] = TRUE;
    $offer_configuration['offer_type'] = 'percentage';
    $offer_configuration['offer_percentage'] = '1';
    $offer_configuration['offer_amount'] = NULL;
    $offer->setConfiguration($offer_configuration);
    $this->promotion->setOffer($offer);

    /** @var \Drupal\commerce_order\OrderItemStorageInterface $order_item_storage */
    $order_item_storage = \Drupal::entityTypeManager()->getStorage('commerce_order_item');
    // We add the same purchasable entity as the auto added one to test the
    // trickier case.
    $first_order_item = $order_item_storage->createFromPurchasableEntity($this->variations[0], [
      'quantity' => '2',
    ]);
    $second_order_item = $order_item_storage->createFromPurchasableEntity($this->variations[2], [
      'quantity' => '1',
    ]);
    $this->order->setItems([$first_order_item, $second_order_item]);
    $this->order->save();

    $first_order_item->setQuantity(3);
    $first_order_item->save();
    $this->container->get('commerce_order.order_refresh')->refresh($this->order);

    $order_items = $this->order->getItems();
    $this->assertCount(3, $order_items);
    // The offer automatically added a third order item.
    [$first_order_item, $second_order_item, $get_order_item] = $order_items;
    $this->assertAdjustmentPrice($get_order_item->getAdjustments()[0], '-30');
    $this->assertEquals('1', $get_order_item->getData("promotion:{$this->promotion->id()}:auto_add_quantity"));

    $first_order_item->setQuantity(2);
    $first_order_item->save();
    $this->container->get('commerce_order.order_refresh')->refresh($this->order);

    $order_items = $this->order->getItems();
    $this->assertCount(2, $order_items);
  }

  /**
   * Tests that the auto-added get order item is automatically removed.
   *
   * @covers ::apply
   * @covers ::clear
   */
  public function testAutoRemoveOrderItem() {
    $offer = $this->promotion->getOffer();
    $offer_configuration = $offer->getConfiguration();
    $offer_configuration['buy_quantity'] = '1';
    $offer_configuration['buy_conditions'] = [
      [
        'plugin' => 'order_item_purchased_entity:commerce_product_variation',
        'configuration' => [
          'entities' => [
            $this->variations[0]->uuid(),
          ],
        ],
      ],
    ];
    // The customer receives 1 specific product for free.
    $offer_configuration['get_quantity'] = '1';
    $offer_configuration['get_conditions'] = [
      [
        'plugin' => 'order_item_purchased_entity:commerce_product_variation',
        'configuration' => [
          'entities' => [
            $this->variations[1]->uuid(),
          ],
        ],
      ],
    ];
    $offer_configuration['get_auto_add'] = TRUE;
    $offer_configuration['offer_type'] = 'percentage';
    $offer_configuration['offer_percentage'] = '1';
    $offer_configuration['offer_amount'] = NULL;
    $offer->setConfiguration($offer_configuration);
    $this->promotion->setOffer($offer);
    $this->promotion->set('conditions', [
      [
        'target_plugin_id' => 'order_total_price',
        'target_plugin_configuration' => [
          'operator' => '>=',
          'amount' => [
            'number' => '15.00',
            'currency_code' => 'USD',
          ],
        ],
      ],
    ]);
    $this->promotion->save();
    $this->variations[1]->setPrice(new Price('15', 'USD'))->save();
    /** @var \Drupal\commerce_order\OrderItemStorageInterface $order_item_storage */
    $order_item_storage = \Drupal::entityTypeManager()->getStorage('commerce_order_item');
    $first_order_item = $order_item_storage->createFromPurchasableEntity($this->variations[0], [
      'quantity' => '2',
    ]);
    $first_order_item->save();
    $this->order->setItems([$first_order_item]);
    $this->container->get('commerce_order.order_refresh')->refresh($this->order);
    [$first_order_item, $second_order_item] = $this->order->getItems();

    $this->assertEquals(2, $first_order_item->getQuantity());
    $this->assertEquals(2, $second_order_item->getQuantity());
    $this->assertCount(1, $second_order_item->getAdjustments());
    $this->assertAdjustmentPrice($second_order_item->getAdjustments()[0], '-30');

    $first_order_item->setQuantity('1');
    $first_order_item->save();
    $this->container->get('commerce_order.order_refresh')->refresh($this->order);
    $this->assertCount(1, $this->order->getItems());
    $this->assertEquals(new Price('10', 'USD'), $this->order->getTotalPrice());

    // Test that a promotion that is no longer applicable is also cleared out.
    $first_order_item->setQuantity('2');
    $first_order_item->save();
    $this->container->get('commerce_order.order_refresh')->refresh($this->order);
    $this->assertCount(2, $this->order->getItems());
    $this->promotion->setEnabled(FALSE);
    $this->promotion->save();
    $this->container->get('commerce_order.order_refresh')->refresh($this->order);
    $this->assertCount(1, $this->order->getItems());

    // Test that a free auto-added order item is automatically cleared out when
    // the promotion offer no longer applies.
    $this->promotion->setEnabled(TRUE);
    $this->promotion->save();

    $this->variations[1]->setPrice(new Price('0', 'USD'));
    $this->variations[1]->save();
    $this->container->get('commerce_order.order_refresh')->refresh($this->order);
    $order_items = $this->order->getItems();
    $this->assertCount(2, $order_items);
    $this->assertEquals(new Price('0', 'USD'), $order_items[1]->getUnitPrice());
    // Disable the promotion, since it no longer applies, the auto-added "get"
    // order item should be removed.
    $this->promotion->setEnabled(FALSE);
    $this->promotion->save();
    $this->container->get('commerce_order.order_refresh')->refresh($this->order);
    $order_items = $this->order->getItems();
    $this->assertCount(1, $order_items);
    $this->assertEquals(new Price('20', 'USD'), $order_items[0]->getTotalPrice());
  }

  /**
   * Tests cumulating multiple BuyXGetY offers.
   *
   * @covers ::apply
   * @covers ::clear
   */
  public function testMultipleOffers() {
    $offer = $this->promotion->getOffer();
    $offer_configuration = $offer->getConfiguration();
    $offer_configuration['buy_quantity'] = '1';
    $offer_configuration['offer_limit'] = '1';
    $offer_configuration['buy_conditions'] = [
      [
        'plugin' => 'order_item_purchased_entity:commerce_product_variation',
        'configuration' => [
          'entities' => [
            $this->variations[0]->uuid(),
          ],
        ],
      ],
    ];
    // The customer receives 1 specific product for free.
    $offer_configuration['get_quantity'] = '1';
    $offer_configuration['get_conditions'] = [
      [
        'plugin' => 'order_item_purchased_entity:commerce_product_variation',
        'configuration' => [
          'entities' => [
            $this->variations[1]->uuid(),
          ],
        ],
      ],
    ];
    $offer_configuration['get_auto_add'] = TRUE;
    $offer_configuration['offer_type'] = 'percentage';
    $offer_configuration['offer_percentage'] = '1';
    $offer_configuration['offer_amount'] = NULL;
    $offer->setConfiguration($offer_configuration);
    $this->promotion->setOffer($offer);

    /** @var \Drupal\commerce_order\OrderItemStorageInterface $order_item_storage */
    $order_item_storage = \Drupal::entityTypeManager()->getStorage('commerce_order_item');
    $first_order_item = $order_item_storage->createFromPurchasableEntity($this->variations[0], [
      'quantity' => '1',
    ]);
    $first_order_item->save();
    $this->order->setItems([$first_order_item]);
    $this->container->get('commerce_order.order_refresh')->refresh($this->order);
    $order_items = $this->order->getItems();
    $this->assertCount(2, $order_items);
    [$first_order_item, $get_order_item] = $order_items;

    $this->assertCount(1, $get_order_item->getAdjustments());
    $this->assertEquals(1, $get_order_item->getQuantity());
    $this->assertEquals($this->variations[1]->id(), $get_order_item->getPurchasedEntityId());
    $this->assertAdjustmentPrice($get_order_item->getAdjustments()[0], '-20');
    $this->assertEquals('1', $get_order_item->getData("promotion:{$this->promotion->id()}:auto_add_quantity"));

    // Create another promotion targeting the same "get" product.
    $another_promotion = $this->promotion->createDuplicate();
    $offer_configuration['buy_conditions'] = [
      [
        'plugin' => 'order_item_purchased_entity:commerce_product_variation',
        'configuration' => [
          'entities' => [
            $this->variations[2]->uuid(),
          ],
        ],
      ],
    ];
    $another_promotion->setOffer($offer);
    $another_promotion->save();

    // Add the second variation to cart, that should give another "get" variation
    // for free.
    $another_order_item = $order_item_storage->createFromPurchasableEntity($this->variations[2], [
      'quantity' => '1',
    ]);
    $another_order_item->save();
    $this->order->setItems([$first_order_item, $another_order_item]);
    $this->container->get('commerce_order.order_refresh')->refresh($this->order);

    $order_items = $this->order->getItems();
    $this->assertCount(3, $order_items);
    [$first_order_item, $another_order_item, $get_order_item] = $order_items;
    $this->assertEquals('2', $get_order_item->getQuantity());
    $promotion_adjustments = $get_order_item->getAdjustments(['promotion']);
    $this->assertCount(2, $promotion_adjustments);
    $this->assertEquals(new Price('-20', 'USD'), $promotion_adjustments[0]->getAmount());
    $this->assertEquals(new Price('-20', 'USD'), $promotion_adjustments[1]->getAmount());
    $this->assertEquals('1', $get_order_item->getData("promotion:{$this->promotion->id()}:auto_add_quantity"));
    $this->assertEquals('1', $get_order_item->getData("promotion:{$another_promotion->id()}:auto_add_quantity"));
    $this->assertTrue($get_order_item->isLocked());

    // Disable the second promotion to ensure the quantity of the "get" order
    // item is adjusted.
    $another_promotion->set('status', FALSE)->save();
    $this->container->get('commerce_order.order_refresh')->refresh($this->order);

    $order_items = $this->order->getItems();
    $this->assertCount(3, $order_items);
    [$first_order_item, $another_order_item, $get_order_item] = $order_items;
    $this->assertEquals('1', $get_order_item->getQuantity());
    $promotion_adjustments = $get_order_item->getAdjustments(['promotion']);
    $this->assertCount(1, $promotion_adjustments);
    $this->assertAdjustmentPrice($promotion_adjustments[0], '-20');
  }

  /**
   * Tests the "display_inclusive" setting.
   *
   * @covers ::apply
   * @covers ::clear
   */
  public function testDisplayInclusive() {
    $offer = $this->promotion->getOffer();
    $offer_configuration = $offer->getConfiguration();
    $offer_configuration['buy_quantity'] = '1';
    $offer_configuration['buy_conditions'] = [];
    $offer_configuration['get_quantity'] = '1';
    $offer_configuration['get_conditions'] = [];
    $offer_configuration['offer_percentage'] = '1';
    $offer_configuration['offer_type'] = 'percentage';
    $offer_configuration['display_inclusive'] = TRUE;
    $offer->setConfiguration($offer_configuration);
    $this->promotion->setOffer($offer);

    /** @var \Drupal\commerce_order\OrderItemStorageInterface $order_item_storage */
    $order_item_storage = \Drupal::entityTypeManager()->getStorage('commerce_order_item');
    $order_item = $order_item_storage->createFromPurchasableEntity($this->variations[0], [
      'quantity' => '2',
    ]);
    $order_item->save();
    $this->order->addItem($order_item);
    $this->order->save();
    $this->container->get('commerce_order.order_refresh')->refresh($this->order);
    [$order_item] = $this->order->getItems();

    $promotion_adjustments = $order_item->getAdjustments(['promotion']);
    $this->assertCount(1, $promotion_adjustments);
    $this->assertAdjustmentPrice($promotion_adjustments[0], '-10');
    $this->assertEquals(new Price('5', 'USD'), $order_item->getUnitPrice());

    $offer_configuration['offer_percentage'] = '0.1';
    $offer->setConfiguration($offer_configuration);
    $this->promotion->setOffer($offer);
    $this->container->get('commerce_order.order_refresh')->refresh($this->order);
    [$order_item] = $this->order->getItems();
    $promotion_adjustments = $order_item->getAdjustments(['promotion']);
    $this->assertCount(1, $promotion_adjustments);
    $this->assertAdjustmentPrice($promotion_adjustments[0], '-1');
    $this->assertEquals(new Price('9.5', 'USD'), $order_item->getUnitPrice());
  }

  /**
   * Asserts that a promotion adjustment has the expected price.
   *
   * @param \Drupal\commerce_order\Adjustment $adjustment
   *   The adjustment to test.
   * @param string $price
   *   The expected price, as a string.
   * @param string $currency_code
   *   The expected currency code.
   */
  protected function assertAdjustmentPrice(Adjustment $adjustment, $price, $currency_code = 'USD') {
    $this->assertEquals('promotion', $adjustment->getType());
    $this->assertEquals(new Price($price, $currency_code), $adjustment->getAmount());
    $this->assertEquals($this->promotion->id(), $adjustment->getSourceId());
  }

}
