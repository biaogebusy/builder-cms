<?php

namespace Drupal\Tests\commerce_promotion\Kernel;

use Drupal\commerce_promotion\Entity\Coupon;
use Drupal\commerce_promotion\Entity\Promotion;
use Drupal\Tests\commerce_order\Kernel\OrderKernelTestBase;

/**
 * Tests the coupon access control.
 *
 * @coversDefaultClass \Drupal\commerce_promotion\CouponAccessControlHandler
 * @group commerce
 */
class CouponAccessTest extends OrderKernelTestBase {

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
    $this->installSchema('commerce_promotion', ['commerce_promotion_usage']);

    // Create uid: 0 and 1 here so that it's skipped in test cases.
    $this->setUpCurrentUser();
  }

  /**
   * @covers ::checkAccess
   */
  public function testAccess() {
    $promotion = Promotion::create([
      'order_types' => ['default'],
      'stores' => [$this->store->id()],
      'status' => TRUE,
    ]);
    $promotion->save();
    /** @var \Drupal\commerce_promotion\Entity\CouponInterface $coupon */
    $coupon = Coupon::create([
      'promotion_id' => $promotion->id(),
      'code' => $this->randomMachineName(),
      'status' => TRUE,
    ]);
    $coupon->save();

    $collection_url = $coupon->toUrl('collection');
    $access_manager = \Drupal::accessManager();

    $account = $this->createUser([], ['access administration pages']);
    $this->assertFalse($coupon->access('view', $account));
    $this->assertFalse($coupon->access('update', $account));
    $this->assertFalse($coupon->access('delete', $account));
    $access = $access_manager->checkNamedRoute($collection_url->getRouteName(), $collection_url->getRouteParameters(), $account, TRUE);
    $this->assertFalse($promotion->access('update', $account));
    $this->assertFalse($access->isAllowed());

    $account = $this->createUser([], ['view commerce_promotion']);
    $this->assertTrue($coupon->access('view', $account));
    $this->assertFalse($coupon->access('update', $account));
    $this->assertFalse($coupon->access('delete', $account));
    $access = $access_manager->checkNamedRoute($collection_url->getRouteName(), $collection_url->getRouteParameters(), $account, TRUE);
    $this->assertFalse($promotion->access('update', $account));
    $this->assertFalse($access->isAllowed());

    $account = $this->createUser([], ['update any commerce_promotion']);
    $this->assertFalse($coupon->access('view', $account));
    $this->assertTrue($coupon->access('update', $account));
    $this->assertTrue($coupon->access('delete', $account));
    $access = $access_manager->checkNamedRoute($collection_url->getRouteName(), $collection_url->getRouteParameters(), $account, TRUE);
    $this->assertTrue($promotion->access('update', $account));
    $this->assertTrue($access->isAllowed());

    $account = $this->createUser([], ['administer commerce_promotion']);
    $this->assertTrue($coupon->access('view', $account));
    $this->assertTrue($coupon->access('update', $account));
    $this->assertTrue($coupon->access('delete', $account));
    $access = $access_manager->checkNamedRoute($collection_url->getRouteName(), $collection_url->getRouteParameters(), $account, TRUE);
    $this->assertTrue($promotion->access('update', $account));
    $this->assertTrue($access->isAllowed());
  }

  /**
   * @covers ::checkCreateAccess
   */
  public function testCreateAccess() {
    $access_control_handler = \Drupal::entityTypeManager()->getAccessControlHandler('commerce_promotion_coupon');

    $account = $this->createUser([], ['access content']);
    $this->assertFalse($access_control_handler->createAccess('test', $account));

    $account = $this->createUser([], ['update any commerce_promotion']);
    $this->assertTrue($access_control_handler->createAccess('test', $account));

    $account = $this->createUser([], ['administer commerce_promotion']);
    $this->assertTrue($access_control_handler->createAccess('test', $account));
  }

}
