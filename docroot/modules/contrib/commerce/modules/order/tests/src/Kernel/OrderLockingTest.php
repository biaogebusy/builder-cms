<?php

namespace Drupal\Tests\commerce_order\Kernel;

use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_order\Exception\OrderVersionMismatchException;
use Drupal\commerce_price\Price;
use Drupal\Core\Entity\EntityStorageException;

/**
 * Tests order locking.
 *
 * @group commerce
 */
class OrderLockingTest extends OrderKernelTestBase {

  /**
   * A test user to be used as orders customer.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->user = $this->createUser(['mail' => 'test@example.com']);
  }

  /**
   * Tests our OrderVersion constraint is available.
   */
  public function testOrderConstraintDefinition() {
    // Ensure our OrderVersion constraint is available.
    $entity_type_manager = $this->container->get('entity_type.manager');
    $order_type = $entity_type_manager->getDefinition('commerce_order');
    $default_constraints = [
      'OrderVersion' => [],
      // Added to all ContentEntity implementations.
      'EntityUntranslatableFields' => NULL,
    ];
    $this->assertEquals($default_constraints, $order_type->getConstraints());
  }

  /**
   * Tests order constraints are validated.
   */
  public function testOrderConstraintValidation() {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = Order::create([
      'type' => 'default',
      'mail' => $this->user->getEmail(),
      'uid' => $this->user->id(),
      'store_id' => $this->store->id(),
    ]);
    $contraint_violations = $order->validate()->getEntityViolations();
    $this->assertEquals(0, $contraint_violations->count());
    $order->save();
    $this->assertEquals(1, $order->getVersion());

    (function ($order_id) {
      $order = Order::load($order_id);
      assert($order instanceof OrderInterface);
      $order->addItem(OrderItem::create([
        'type' => 'test',
        'quantity' => 1,
        'unit_price' => new Price('12.00', 'USD'),
      ]));
      $order->save();
      $this->assertEquals(2, $order->getVersion());
    })($order->id());

    $contraint_violations = $order->validate()->getEntityViolations();
    $this->assertEquals(1, $contraint_violations->count());
    $entity_constraint_violation = $contraint_violations->get(0);
    $this->assertEquals('The order has either been modified by another user, or you have already submitted modifications. As a result, your changes cannot be saved.', $entity_constraint_violation->getMessage());
  }

  /**
   * Tests exception is thrown on save.
   */
  public function testOrderVersionMismatchException() {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = Order::create([
      'type' => 'default',
      'mail' => $this->user->getEmail(),
      'uid' => $this->user->id(),
      'store_id' => $this->store->id(),
    ]);
    $order->save();
    $this->assertEquals(1, $order->getVersion());

    (function ($order_id) {
      $order = Order::load($order_id);
      assert($order instanceof OrderInterface);
      $order->addItem(OrderItem::create([
        'type' => 'test',
        'quantity' => 1,
        'unit_price' => new Price('12.00', 'USD'),
      ]));
      $order->save();
      $this->assertEquals(2, $order->getVersion());
    })($order->id());

    try {
      $order->save();
      $this->fail('Expected OrderVersionMismatchException exception');
    }
    catch (EntityStorageException $e) {
      $this->assertEquals(sprintf('Attempted to save order %s with version %s. Current version is %s.', $order->id(), $order->getVersion(), $order->original->getVersion()), $e->getMessage());
      $original = $e->getPrevious();
      $this->assertInstanceOf(OrderVersionMismatchException::class, $original);
    }
  }

}
