<?php

namespace Drupal\Tests\commerce_promotion\Kernel;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Tests\commerce_order\Kernel\OrderKernelTestBase;
use Drupal\commerce_promotion\Entity\Coupon;

/**
 * Tests coupon validation constraints.
 *
 * @group commerce
 */
class CouponValidationTest extends OrderKernelTestBase {

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

    $this->installEntitySchema('commerce_promotion_coupon');
  }

  /**
   * Tests the coupon code constraint.
   */
  public function testUniqueness() {
    $coupon_code = $this->randomMachineName();
    $coupon = Coupon::create([
      'code' => $coupon_code,
      'status' => TRUE,
    ]);
    $violations = $coupon->validate();
    $this->assertEquals(count($violations), 0);
    $coupon->save();

    $coupon = Coupon::create([
      'code' => $coupon_code,
      'status' => TRUE,
    ]);
    $expected_message = new FormattableMarkup('Coupon code matching is case-insensitive, and codes must be unique. %value cannot be used because it matches an existing code.', [
      '%value' => $coupon_code,
    ]);
    $violations = $coupon->validate();
    $this->assertCount(1, $violations);
    $this->assertEquals($violations[0]->getPropertyPath(), 'code');
    $this->assertEquals((string) $violations[0]->getMessage(), $expected_message->__toString());

    $coupon->setCode($coupon_code . 'X');
    $violations = $coupon->validate();
    $this->assertCount(0, $violations);
  }

  /**
   * Tests the coupon code case-insensitive.
   */
  public function testCaseSensitivity() {
    $uppercase_code = 'ABCD';
    $coupon = Coupon::create([
      'code' => $uppercase_code,
      'status' => TRUE,
    ]);
    $violations = $coupon->validate();
    $this->assertEquals(count($violations), 0);
    $coupon->save();
    $lower_case_code = 'abcd';
    $coupon = Coupon::create([
      'code' => $lower_case_code,
      'status' => TRUE,
    ]);
    $expected_message = new FormattableMarkup('Coupon code matching is case-insensitive, and codes must be unique. %value cannot be used because it matches an existing code.', [
      '%value' => $lower_case_code,
    ]);
    $violations = $coupon->validate();
    $this->assertCount(1, $violations);
    $this->assertEquals($violations[0]->getPropertyPath(), 'code');
    $this->assertEquals((string) $violations[0]->getMessage(), $expected_message->__toString());
  }

}
