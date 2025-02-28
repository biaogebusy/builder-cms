<?php

namespace Drupal\Tests\commerce_order\Unit\Plugin\Commerce\Condition;

use CommerceGuys\Addressing\Address;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Plugin\Commerce\Condition\OrderShippingAddress;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\profile\Entity\ProfileInterface;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\commerce_order\Plugin\Commerce\Condition\OrderShippingAddress
 * @group commerce
 */
class OrderShippingAddressTest extends UnitTestCase {

  /**
   * ::covers evaluate.
   */
  public function testIncompleteOrder() {
    $condition = new OrderShippingAddress([
      'zone' => [
        'territories' => [
          ['country_code' => 'US', 'administrative_area' => 'CA'],
        ],
      ],
    ], 'order_shipping_address', ['entity_type' => 'commerce_order', 'profile_scope' => 'shipping']);
    $order = $this->prophesize(OrderInterface::class);
    $order->getEntityTypeId()->willReturn('commerce_order');
    $order->collectProfiles()->willReturn([]);
    $order = $order->reveal();

    $this->assertFalse($condition->evaluate($order));
  }

  /**
   * ::covers evaluate.
   */
  public function testEvaluate() {
    $address_list = $this->prophesize(FieldItemListInterface::class);
    $address_list->first()->willReturn(new Address('US', 'SC'));
    $address_list = $address_list->reveal();
    $shipping_profile = $this->prophesize(ProfileInterface::class);
    $shipping_profile->get('address')->willReturn($address_list);
    $shipping_profile = $shipping_profile->reveal();
    $order = $this->prophesize(OrderInterface::class);
    $order->getEntityTypeId()->willReturn('commerce_order');
    $order->collectProfiles()->willReturn(['shipping' => $shipping_profile]);
    $order = $order->reveal();

    $condition = new OrderShippingAddress([
      'zone' => [
        'territories' => [
          ['country_code' => 'US', 'administrative_area' => 'CA'],
        ],
      ],
    ], 'order_shipping_address', ['entity_type' => 'commerce_order', 'profile_scope' => 'shipping']);
    $this->assertFalse($condition->evaluate($order));

    $condition = new OrderShippingAddress([
      'zone' => [
        'territories' => [
          ['country_code' => 'US', 'administrative_area' => 'SC'],
        ],
      ],
    ], 'order_shipping_address', ['entity_type' => 'commerce_order', 'profile_scope' => 'shipping']);
    $this->assertTrue($condition->evaluate($order));
  }

}
