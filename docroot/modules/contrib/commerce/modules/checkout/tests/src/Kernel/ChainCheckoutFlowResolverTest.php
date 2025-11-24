<?php

namespace Drupal\Tests\commerce_checkout\Kernel;

use Drupal\Tests\commerce_order\Kernel\OrderKernelTestBase;
use Drupal\commerce_order\Entity\Order;

/**
 * Tests the chain checkout flow resolver.
 *
 * @group commerce
 */
class ChainCheckoutFlowResolverTest extends OrderKernelTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'commerce_checkout',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig('commerce_checkout');
  }

  /**
   * Tests resolving the checkout flow.
   */
  public function testCheckoutFlowResolution() {
    $user = $this->createUser();
    $order = Order::create([
      'type' => 'default',
      'mail' => $user->getEmail(),
      'uid' => $user->id(),
      'store_id' => $this->store->id(),
    ]);
    $order->save();

    $resolver = $this->container->get('commerce_checkout.chain_checkout_flow_resolver');
    /** @var \Drupal\commerce_checkout\Entity\CheckoutFlowInterface $checkout_flow */
    $checkout_flow = $resolver->resolve($order);

    $this->assertEquals('default', $checkout_flow->id());
  }

}
