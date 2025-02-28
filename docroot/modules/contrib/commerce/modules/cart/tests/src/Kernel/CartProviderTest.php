<?php

namespace Drupal\Tests\commerce_cart\Kernel;

use Drupal\commerce_cart\Exception\DuplicateCartException;
use Drupal\commerce_order\Entity\OrderInterface;

/**
 * Tests the cart provider.
 *
 * @coversDefaultClass \Drupal\commerce_cart\CartProvider
 * @group commerce
 */
class CartProviderTest extends CartKernelTestBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Anonymous user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $anonymousUser;

  /**
   * Registered user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $authenticatedUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->entityTypeManager = $this->container->get('entity_type.manager');
    $this->anonymousUser = $this->createUser([
      'uid' => 0,
      'name' => '',
      'status' => 0,
    ]);
    $this->authenticatedUser = $this->createUser();
  }

  /**
   * Tests cart creation for an anonymous user.
   *
   * @covers ::createCart
   */
  public function testCreateAnonymousCart() {
    $order_type = 'default';
    $cart = $this->cartProvider->createCart($order_type, $this->store, $this->anonymousUser);
    $this->assertInstanceOf(OrderInterface::class, $cart);

    // Trying to recreate the same cart should throw an exception.
    $this->expectException(DuplicateCartException::class);
    $this->cartProvider->createCart($order_type, $this->store, $this->anonymousUser);
  }

  /**
   * Tests getting an anonymous user's cart.
   *
   * @covers ::getCart
   * @covers ::getCartId
   * @covers ::getCarts
   * @covers ::getCartIds
   */
  public function testGetAnonymousCart() {
    $this->cartProvider->createCart('default', $this->store, $this->anonymousUser);
    $cart = $this->cartProvider->getCart('default', $this->store, $this->anonymousUser);
    $this->assertInstanceOf(OrderInterface::class, $cart);

    $cart_id = $this->cartProvider->getCartId('default', $this->store, $this->anonymousUser);
    $this->assertEquals(1, $cart_id);

    $carts = $this->cartProvider->getCarts($this->anonymousUser);
    $this->assertContainsOnlyInstancesOf(OrderInterface::class, $carts);

    $this->assertContains(1, $this->cartProvider->getCartIds($this->anonymousUser, $this->store));
    $this->assertContains(1, $this->cartProvider->getCartIds($this->anonymousUser));

    // Tests passing a different store.
    $another_store = $this->createStore();
    $another_cart = $this->cartProvider->createCart('default', $another_store, $this->anonymousUser);
    $another_cart = $this->reloadEntity($another_cart);
    $this->assertInstanceOf(OrderInterface::class, $another_cart);
    $this->assertEquals($another_cart->id(), $this->cartProvider->getCartId('default', $another_store, $this->anonymousUser));
    $carts = $this->cartProvider->getCarts($this->anonymousUser, $another_store);
    $this->assertEquals($another_cart, reset($carts));
    $this->assertContains((int) $another_cart->id(), $this->cartProvider->getCartIds($this->anonymousUser, $another_store));

    // Test that 2 carts are returned when omitting the store parameter.
    $this->assertCount(2, $this->cartProvider->getCartIds($this->anonymousUser));
  }

  /**
   * Tests creating a cart for an authenticated user.
   *
   * @covers ::createCart
   */
  public function testCreateAuthenticatedCart() {
    $cart = $this->cartProvider->createCart('default', $this->store, $this->authenticatedUser);
    $this->assertInstanceOf(OrderInterface::class, $cart);

    // Trying to recreate the same cart should throw an exception.
    $this->expectException(DuplicateCartException::class);
    $this->cartProvider->createCart('default', $this->store, $this->authenticatedUser);
  }

  /**
   * Tests getting an authenticated user's cart.
   *
   * @covers ::getCart
   * @covers ::getCartId
   * @covers ::getCarts
   * @covers ::getCartIds
   */
  public function testGetAuthenticatedCart() {
    $this->cartProvider->createCart('default', $this->store, $this->authenticatedUser);

    $cart = $this->cartProvider->getCart('default', $this->store, $this->authenticatedUser);
    $this->assertInstanceOf(OrderInterface::class, $cart);

    $cart_id = $this->cartProvider->getCartId('default', $this->store, $this->authenticatedUser);
    $this->assertEquals(1, $cart_id);

    $carts = $this->cartProvider->getCarts($this->authenticatedUser);
    $this->assertContainsOnlyInstancesOf(OrderInterface::class, $carts);

    $this->assertContains(1, $this->cartProvider->getCartIds($this->authenticatedUser, $this->store));
    $this->assertContains(1, $this->cartProvider->getCartIds($this->authenticatedUser));

    $another_store = $this->createStore();
    $another_cart = $this->cartProvider->createCart('default', $another_store, $this->authenticatedUser);
    $another_cart = $this->reloadEntity($another_cart);
    $this->assertInstanceOf(OrderInterface::class, $another_cart);
    $this->assertEquals($another_cart->id(), $this->cartProvider->getCartId('default', $another_store, $this->authenticatedUser));
    $carts = $this->cartProvider->getCarts($this->authenticatedUser, $another_store);
    $this->assertEquals($another_cart, reset($carts));
    $this->assertContains((int) $another_cart->id(), $this->cartProvider->getCartIds($this->authenticatedUser, $another_store));

    // Test that 2 carts are returned when omitting the store parameter.
    $this->assertCount(2, $this->cartProvider->getCartIds($this->authenticatedUser));
  }

  /**
   * Tests finalizing a cart.
   *
   * @covers ::finalizeCart
   */
  public function testFinalizeCart() {
    $cart = $this->cartProvider->createCart('default', $this->store, $this->authenticatedUser);

    $this->cartProvider->finalizeCart($cart);
    $cart = $this->reloadEntity($cart);
    $this->assertEmpty($cart->cart->value);

    $cart = $this->cartProvider->getCart('default', $this->store, $this->authenticatedUser);
    $this->assertNull($cart);
  }

  /**
   * Tests cart validation.
   *
   * @covers ::getCartIds
   * @covers ::clearCaches
   */
  public function testCartValidation() {
    // Locked carts should not be returned.
    $cart = $this->cartProvider->createCart('default', $this->store, $this->authenticatedUser);
    $cart->lock();
    $cart->save();
    $this->cartProvider->clearCaches();
    $cart = $this->cartProvider->getCart('default', $this->store, $this->authenticatedUser);
    $this->assertNull($cart);

    // Carts that are no longer carts should not be returned.
    $cart = $this->cartProvider->createCart('default', $this->store, $this->authenticatedUser);
    $cart->cart = FALSE;
    $cart->save();
    $this->cartProvider->clearCaches();
    $cart = $this->cartProvider->getCart('default', $this->store, $this->authenticatedUser);
    $this->assertNull($cart);

    // Carts assigned to a different user should not be returned.
    $cart = $this->cartProvider->createCart('default', $this->store, $this->authenticatedUser);
    $cart->uid = $this->anonymousUser->id();
    $cart->save();
    $this->cartProvider->clearCaches();
    $cart = $this->cartProvider->getCart('default', $this->store, $this->authenticatedUser);
    $this->assertNull($cart);

    // Canceled carts should not be returned.
    $cart = $this->cartProvider->createCart('default', $this->store, $this->authenticatedUser);
    $cart->state = 'canceled';
    $cart->save();
    $this->cartProvider->clearCaches();
    $cart = $this->cartProvider->getCart('default', $this->store, $this->authenticatedUser);
    $this->assertNull($cart);
  }

}
