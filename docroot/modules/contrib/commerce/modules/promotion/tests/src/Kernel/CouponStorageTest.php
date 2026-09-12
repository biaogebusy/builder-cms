<?php

namespace Drupal\Tests\commerce_promotion\Kernel;

use Drupal\commerce_promotion\CouponStorageInterface;
use Drupal\Tests\commerce_order\Kernel\OrderKernelTestBase;
use Drupal\commerce_promotion\Entity\Coupon;

/**
 * Tests coupon storage.
 *
 * @group commerce
 */
class CouponStorageTest extends OrderKernelTestBase {

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
  }

  /**
   * Loads a coupon by its code.
   */
  public function testLoadEnabledByCode() {
    $coupon_code = $this->randomMachineName();
    $coupon = Coupon::create([
      'code' => $coupon_code,
      'status' => TRUE,
    ]);
    $coupon->save();

    $coupon_storage = $this->entityTypeManager->getStorage('commerce_promotion_coupon');
    assert($coupon_storage instanceof CouponStorageInterface);
    $coupon_loaded = $coupon_storage->loadEnabledByCode($coupon_code);
    $this->assertEquals($coupon->id(), $coupon_loaded->id());

    $coupon_code = $this->randomMachineName();
    $coupon = Coupon::create([
      'code' => $coupon_code,
      'status' => FALSE,
    ]);
    $coupon->save();

    $coupon_loaded = $coupon_storage->loadEnabledByCode($coupon_code);
    $this->assertEmpty($coupon_loaded);
  }

}
