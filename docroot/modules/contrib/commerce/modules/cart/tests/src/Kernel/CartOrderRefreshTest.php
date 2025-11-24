<?php

namespace Drupal\Tests\commerce_cart\Kernel;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Core\Session\AnonymousUserSession;
use Drupal\commerce_order\Entity\OrderType;
use Drupal\commerce_order\Entity\OrderTypeInterface;
use Drupal\commerce_order\OrderRefresh;

/**
 * Tests the order refresh process.
 *
 * @group commerce_cart
 */
class CartOrderRefreshTest extends CartKernelTestBase {

  /**
   * Anonymous user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $anonymousUser;

  /**
   * A sample cart.
   *
   * @var \Drupal\commerce_order\Entity\OrderInterface
   */
  protected $cart;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'commerce_order_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $anonymous_user = new AnonymousUserSession();
    $this->anonymousUser = $anonymous_user;
    $this->container->get('current_user')->setAccount($this->anonymousUser);
    $cart = $this->cartProvider->createCart('default', $this->store, $anonymous_user);
    $cart->save();
    $this->cart = $this->reloadEntity($cart);
  }

  /**
   * Tests the shouldRefresh() logic.
   */
  public function testShouldRefresh(): void {
    $order_refresh = $this->createOrderRefresh(time() + 3600);

    $order_type = OrderType::load($this->cart->bundle());
    $order_type->setRefreshMode(OrderTypeInterface::REFRESH_CUSTOMER)->save();
    // Cart belongs to the current anonymous user.
    $this->assertNotEmpty($order_refresh->shouldRefresh($this->cart));
    // Current user is a different anonymous user.
    $this->container->get('current_user')->setAccount(new AnonymousUserSession());
    // Ideally, this would destroy the current session and create a new one.
    // It doesn't, so we will simulate it, by removing the order id
    // from the cart session.
    \Drupal::service('commerce_cart.cart_session')->deleteCartId($this->cart->id());
    // Deleting the cart id from the session is not enough, as the entity
    // access control handler has its own cache, which will return the cached
    // result from the previous shouldRefresh call above.
    // We need to reset that cache so that _commerce_cart_order_access()
    // is invoked.
    $this->entityTypeManager->getAccessControlHandler('commerce_order')->resetCache();
    // We should appear as we are in a different session, at least from the
    // perspective of commerce_cart.cart_session.
    $this->assertFalse($order_refresh->shouldRefresh($this->cart));
  }

  /**
   * Creates an OrderRefresh instance with the given current time.
   *
   * @param int|null $current_time
   *   The current time as a UNIX timestamp. Defaults to time().
   *
   * @return \Drupal\commerce_order\OrderRefreshInterface
   *   The order refresh.
   */
  protected function createOrderRefresh(?int $current_time = NULL) {
    $current_time = $current_time ?: time();
    $entity_type_manager = $this->container->get('entity_type.manager');
    $chain_price_resolver = $this->container->get('commerce_price.chain_price_resolver');
    $user = $this->container->get('current_user');
    $time = $this->prophesize(TimeInterface::class);
    $time->getCurrentTime()->willReturn($current_time);
    $time = $time->reveal();
    $order_refresh = new OrderRefresh($entity_type_manager, $chain_price_resolver, $user, $time);
    $order_refresh->addProcessor($this->container->get('commerce_order.availability_order_processor'));

    return $order_refresh;
  }

}
