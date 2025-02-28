<?php

namespace Drupal\Tests\commerce_promotion\Kernel;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_promotion\Entity\Promotion;
use Drupal\commerce_promotion\PromotionStorageInterface;
use Drupal\profile\Entity\Profile;
use Drupal\Tests\commerce_order\Kernel\OrderKernelTestBase;

/**
 * Tests the FilterPromotionsEvent.
 *
 * @group commerce
 */
class FilterPromotionsEventTest extends OrderKernelTestBase {

  /**
   * The promotion storage.
   *
   * @var \Drupal\commerce_promotion\PromotionStorageInterface
   */
  protected PromotionStorageInterface $storage;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'commerce_promotion',
    'commerce_promotion_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('commerce_promotion');
    $this->storage = $this->container->get('entity_type.manager')->getStorage('commerce_promotion');
  }

  /**
   * Tests that the proper promotion is filtered out.
   */
  public function testEvent() {
    $promotion_example = Promotion::create([
      'order_types' => ['default'],
      'name' => 'Example',
      'stores' => [$this->store->id()],
      'status' => TRUE,
    ]);
    $promotion_example->save();
    $promotion_filtered = Promotion::create([
      'name' => 'Example (Filtered)',
      'order_types' => ['default'],
      'stores' => [$this->store->id()],
      'status' => TRUE,
    ]);
    $promotion_filtered->save();

    $profile = Profile::create([
      'type' => 'customer',
    ]);
    $profile->save();
    $profile = $this->reloadEntity($profile);

    $user = $this->createUser();
    $order = Order::create([
      'type' => 'default',
      'state' => 'draft',
      'mail' => $user->getEmail(),
      'uid' => $user->id(),
      'ip_address' => '127.0.0.1',
      'order_number' => '6',
      'billing_profile' => $profile,
      'store_id' => $this->store->id(),
    ]);
    $order->save();

    $available_promotions = $this->storage->loadAvailable($order);
    $this->assertEquals(2, count($available_promotions));
    $promotion = array_shift($available_promotions);
    $this->assertEquals($promotion_example->label(), $promotion->label());
    $promotion = array_shift($available_promotions);
    $this->assertEquals($promotion_filtered->label(), $promotion->label());

    $order->setData('excluded_promotions', [$promotion_filtered->id()]);

    $available_promotions = $this->storage->loadAvailable($order);
    $this->assertEquals(1, count($available_promotions));
    $promotion = array_shift($available_promotions);
    $this->assertEquals($promotion_example->label(), $promotion->label());
  }

}
