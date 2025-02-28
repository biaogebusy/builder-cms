<?php

namespace Drupal\Tests\commerce_promotion\Kernel\Entity;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_price\Price;
use Drupal\commerce_promotion\Entity\Coupon;
use Drupal\commerce_promotion\Entity\Promotion;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\datetime\Plugin\Field\FieldType\DateTimeItemInterface;
use Drupal\Tests\commerce_order\Kernel\OrderKernelTestBase;

/**
 * Tests the Coupon entity.
 *
 * @coversDefaultClass \Drupal\commerce_promotion\Entity\Coupon
 *
 * @group commerce
 */
class CouponTest extends OrderKernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'commerce_promotion',
  ];

  /**
   * A test order.
   *
   * @var \Drupal\commerce_order\Entity\OrderInterface
   */
  protected $order;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('commerce_promotion');
    $this->installEntitySchema('commerce_promotion_coupon');
    $this->installSchema('commerce_promotion', ['commerce_promotion_usage']);
    $this->installConfig(['commerce_promotion']);

    $order_item = OrderItem::create([
      'type' => 'test',
      'quantity' => 1,
      'unit_price' => new Price('12.00', 'USD'),
    ]);
    $order = Order::create([
      'type' => 'default',
      'state' => 'draft',
      'mail' => 'test@example.com',
      'ip_address' => '127.0.0.1',
      'order_number' => '6',
      'store_id' => $this->store,
      'uid' => $this->createUser(),
      'order_items' => [$order_item],
      // Used when determining availability, via $order->getCalculationDate().
      'placed' => strtotime('2019-11-15 10:14:00'),
    ]);
    $order->setRefreshState(Order::REFRESH_SKIP);
    $order->save();
    $this->order = $this->reloadEntity($order);
  }

  /**
   * @covers ::getPromotion
   * @covers ::getPromotionId
   * @covers ::getCode
   * @covers ::setCode
   * @covers ::getCreatedTime
   * @covers ::setCreatedTime
   * @covers ::getUsageLimit
   * @covers ::setUsageLimit
   * @covers ::getCustomerUsageLimit
   * @covers ::setCustomerUsageLimit
   * @covers ::isEnabled
   * @covers ::setEnabled
   * @covers ::getStartDate
   * @covers ::setStartDate
   * @covers ::getEndDate
   * @covers ::setEndDate
   */
  public function testCoupon() {
    $promotion = Promotion::create([
      'status' => FALSE,
    ]);
    $promotion->save();
    $promotion = $this->reloadEntity($promotion);

    $coupon = Coupon::create([
      'status' => FALSE,
      'promotion_id' => $promotion->id(),
    ]);

    $this->assertEquals($promotion, $coupon->getPromotion());
    $this->assertEquals($promotion->id(), $coupon->getPromotionId());

    $coupon->setCode('test_code');
    $this->assertEquals('test_code', $coupon->getCode());

    $coupon->setCreatedTime(635879700);
    $this->assertEquals(635879700, $coupon->getCreatedTime());

    $coupon->setUsageLimit(10);
    $this->assertEquals(10, $coupon->getUsageLimit());

    $coupon->setCustomerUsageLimit(1);
    $this->assertEquals(1, $coupon->getCustomerUsageLimit());

    $coupon->setEnabled(TRUE);
    $this->assertEquals(TRUE, $coupon->isEnabled());

    $date_pattern = DateTimeItemInterface::DATETIME_STORAGE_FORMAT;
    $time = $this->container->get('datetime.time');
    $default_start_date = date($date_pattern, $time->getRequestTime());
    $this->assertEquals($default_start_date, $coupon->getStartDate()->format($date_pattern));
    $coupon->setStartDate(new DrupalDateTime('2017-01-01 12:12:12'));
    $this->assertEquals('2017-01-01 12:12:12 UTC', $coupon->getStartDate()->format('Y-m-d H:i:s T'));
    $this->assertEquals('2017-01-01 12:12:12 CET', $coupon->getStartDate('Europe/Berlin')->format('Y-m-d H:i:s T'));

    $this->assertNull($coupon->getEndDate());
    $coupon->setEndDate(new DrupalDateTime('2017-01-31 17:15:00'));
    $this->assertEquals('2017-01-31 17:15:00 UTC', $coupon->getEndDate()->format('Y-m-d H:i:s T'));
    $this->assertEquals('2017-01-31 17:15:00 CET', $coupon->getEndDate('Europe/Berlin')->format('Y-m-d H:i:s T'));
  }

  /**
   * @covers ::available
   */
  public function testAvailability() {
    $promotion = Promotion::create([
      'order_types' => ['default'],
      'stores' => [$this->store->id()],
      'usage_limit' => 1,
      'usage_limit_customer' => 1,
      'start_date' => '2017-01-01',
      'status' => TRUE,
    ]);
    $promotion->save();

    /** @var \Drupal\commerce_promotion\Entity\CouponInterface $coupon */
    $coupon = Coupon::create([
      'promotion_id' => $promotion->id(),
      'code' => 'coupon_code',
      'status' => TRUE,
    ]);
    $coupon->setStartDate(DrupalDateTime::createFromTimestamp($this->order->getPlacedTime()));
    $coupon->save();
    $this->assertTrue($coupon->available($this->order));

    $coupon->setEnabled(FALSE);
    $this->assertFalse($coupon->available($this->order));
    $coupon->setEnabled(TRUE);

    $this->container->get('commerce_promotion.usage')->register($this->order, $promotion, $coupon);
    // Test that the promotion usage is checked at the coupon level.
    $this->assertFalse($coupon->available($this->order));

    $promotion->setUsageLimit(0);
    $promotion->setCustomerUsageLimit(0);
    $promotion->save();
    $promotion = $this->reloadEntity($promotion);
    $this->assertTrue($coupon->available($this->order));

    // Test the global coupon usage limit.
    $coupon->setUsageLimit(1);
    $this->assertFalse($coupon->available($this->order));

    // Test limit coupon usage by customer.
    $coupon->setCustomerUsageLimit(1);
    $coupon->setUsageLimit(0);
    $coupon->save();
    $coupon = $this->reloadEntity($coupon);
    $this->assertFalse($coupon->available($this->order));

    $this->order->setEmail('another@example.com');
    $this->order->setRefreshState(Order::REFRESH_SKIP);
    $this->order->save();
    $this->order = $this->reloadEntity($this->order);
    $this->assertTrue($coupon->available($this->order));

    \Drupal::service('commerce_promotion.usage')->register($this->order, $promotion, $coupon);
    $this->assertFalse($coupon->available($this->order));
  }

  /**
   * @covers ::available
   */
  public function testAvailabilityAllStores() {
    $promotion = Promotion::create([
      'order_types' => ['default'],
      'stores' => [],
      'usage_limit' => 1,
      'start_date' => '2017-01-01',
      'status' => TRUE,
    ]);
    $promotion->save();

    $coupon = Coupon::create([
      'promotion_id' => $promotion->id(),
      'code' => 'coupon_code',
      'usage_limit' => 1,
      'status' => TRUE,
    ]);
    $coupon->setStartDate(DrupalDateTime::createFromTimestamp($this->order->getPlacedTime()));
    $coupon->save();
    $this->assertTrue($coupon->available($this->order));

    $coupon->setEnabled(FALSE);
    $this->assertFalse($coupon->available($this->order));
    $coupon->setEnabled(TRUE);

    \Drupal::service('commerce_promotion.usage')->register($this->order, $promotion, $coupon);
    $this->assertFalse($coupon->available($this->order));
  }

  /**
   * Tests the start date logic.
   */
  public function testStartDate() {
    $promotion = Promotion::create([
      'order_types' => ['default'],
      'stores' => [],
      'usage_limit' => 1,
      'start_date' => '2017-01-01',
      'status' => TRUE,
    ]);
    $promotion->save();

    $coupon = Coupon::create([
      'promotion_id' => $promotion->id(),
      'code' => 'coupon_code',
      'status' => TRUE,
    ]);
    // Start date equal to the order placed date.
    $date = new DrupalDateTime('2019-11-15 10:14:00');
    $coupon->setStartDate($date);
    $this->assertTrue($coupon->available($this->order));

    // Past start date.
    $date = new DrupalDateTime('2019-11-10 10:14:00');
    $coupon->setStartDate($date);
    $this->assertTrue($coupon->available($this->order));

    // Future start date.
    $date = new DrupalDateTime('2019-11-20 10:14:00');
    $coupon->setStartDate($date);
    $this->assertFalse($coupon->available($this->order));
  }

  /**
   * Tests the end date logic.
   */
  public function testEndDate() {
    // No end date date.
    $promotion = Promotion::create([
      'order_types' => ['default'],
      'stores' => [$this->store->id()],
      'usage_limit' => 1,
      'usage_limit_customer' => 0,
      'start_date' => '2019-01-01T00:00:00',
      'status' => TRUE,
    ]);
    $promotion->save();
    $this->assertTrue($promotion->available($this->order));
    $coupon = Coupon::create([
      'promotion_id' => $promotion->id(),
      'code' => 'coupon_code',
      'status' => TRUE,
    ]);
    $coupon->setStartDate(DrupalDateTime::createFromTimestamp($this->order->getPlacedTime()));
    $this->assertTrue($coupon->available($this->order));
    // End date equal to the order placed date.
    $date = new DrupalDateTime('2019-11-15 10:14:00');
    $coupon->setEndDate($date);
    $this->assertFalse($coupon->available($this->order));

    // Past end date.
    $date = new DrupalDateTime('2017-01-01 00:00:00');
    $coupon->setEndDate($date);
    $this->assertFalse($coupon->available($this->order));

    // Future end date.
    $date = new DrupalDateTime('2019-11-20 10:14:00');
    $coupon->setEndDate($date);
    $this->assertTrue($coupon->available($this->order));
  }

}
