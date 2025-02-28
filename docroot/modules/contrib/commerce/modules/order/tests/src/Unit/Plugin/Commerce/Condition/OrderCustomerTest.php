<?php

namespace Drupal\Tests\commerce_order\Unit\Plugin\Commerce\Condition;

use Drupal\commerce\EntityUuidMapperInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Plugin\Commerce\Condition\OrderCustomer;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\user\UserInterface;

/**
 * @coversDefaultClass \Drupal\commerce_order\Plugin\Commerce\Condition\OrderCustomer
 * @group commerce
 */
class OrderCustomerTest extends UnitTestCase {

  /**
   * ::covers evaluate.
   */
  public function testEvaluate() {
    $entity_type_manager = $this->prophesize(EntityTypeManagerInterface::class);
    $entity_type_manager = $entity_type_manager->reveal();
    $entity_uuid_mapper = $this->prophesize(EntityUuidMapperInterface::class);
    $entity_uuid_mapper = $entity_uuid_mapper->reveal();

    $condition = new OrderCustomer([
      'entities' => [],
    ], 'order_customer', ['entity_type' => 'commerce_order'], $entity_type_manager, $entity_uuid_mapper);

    $customer = $this->prophesize(UserInterface::class);
    $customer->uuid()->willReturn('');
    $customer = $customer->reveal();

    $order = $this->buildOrder($customer);
    $this->assertFalse($condition->evaluate($order));

    // Set two UUIDs, one of which will match the customer.
    $condition->setConfiguration(['entities' => ['20363dff-479a-40a1-afcb-1f5f7b9e280c', '2141473e-c62a-455e-9388-4cf3858fbde1']]);
    $customer = $this->prophesize(UserInterface::class);
    $customer->uuid()->willReturn('20363dff-479a-40a1-afcb-1f5f7b9e280c');
    $customer = $customer->reveal();
    $order = $this->buildOrder($customer);
    $this->assertTrue($condition->evaluate($order));

    // Set two UUIDs that won't match the customer.
    $condition->setConfiguration(['entities' => ['6c0556de-b867-4fd1-8d03-56c818944bfc', 'd540ae0e-ec45-4ca8-91d7-7882ccafbf57']]);
    $order = $this->buildOrder($customer);
    $this->assertFalse($condition->evaluate($order));
  }

  /**
   * Builds a mock order with the given customer.
   *
   * @param \Drupal\Core\Session\AccountInterface $customer
   *   The customer account.
   *
   * @return \Drupal\commerce_order\Entity\OrderInterface
   *   The mock order.
   */
  protected function buildOrder(AccountInterface $customer) {
    $order = $this->prophesize(OrderInterface::class);
    $order->getEntityTypeId()->willReturn('commerce_order');
    $order->getCustomer()->willReturn($customer);
    $order = $order->reveal();

    return $order;
  }

}
