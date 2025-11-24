<?php

namespace Drupal\Tests\commerce_order\Unit\Plugin\Commerce\Condition;

use CommerceGuys\Addressing\Address;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_order\Plugin\Commerce\Condition\OrderBillingAddress;
use Drupal\profile\Entity\ProfileInterface;

/**
 * @coversDefaultClass \Drupal\commerce_order\Plugin\Commerce\Condition\OrderBillingAddress
 * @group commerce
 */
class OrderBillingAddressTest extends UnitTestCase {

  /**
   * ::covers evaluate.
   */
  public function testIncompleteOrder() {
    $condition = new OrderBillingAddress([
      'zone' => [
        'territories' => [
          ['country_code' => 'US', 'administrative_area' => 'CA'],
        ],
      ],
    ], 'order_billing_address', ['entity_type' => 'commerce_order', 'profile_scope' => 'billing']);
    $order = $this->prophesize(OrderInterface::class);
    $order->getEntityTypeId()->willReturn('commerce_order');
    $order->collectProfiles()->willReturn([]);
    $order->getBillingProfile()->willReturn(NULL);
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
    $billing_profile = $this->prophesize(ProfileInterface::class);
    $billing_profile->get('address')->willReturn($address_list);
    $billing_profile = $billing_profile->reveal();
    $order = $this->prophesize(OrderInterface::class);
    $order->getEntityTypeId()->willReturn('commerce_order');
    $order->collectProfiles()->willReturn(['billing' => $billing_profile]);
    $order = $order->reveal();

    $condition = new OrderBillingAddress([
      'zone' => [
        'territories' => [
          ['country_code' => 'US', 'administrative_area' => 'CA'],
        ],
      ],
    ], 'order_billing_address', ['entity_type' => 'commerce_order', 'profile_scope' => 'billing']);
    $this->assertFalse($condition->evaluate($order));

    $condition = new OrderBillingAddress([
      'zone' => [
        'territories' => [
          ['country_code' => 'US', 'administrative_area' => 'CA'],
        ],
      ],
      'operator' => 'not in',
    ], 'order_billing_address', ['entity_type' => 'commerce_order', 'profile_scope' => 'billing']);
    $this->assertTrue($condition->evaluate($order));

    $condition = new OrderBillingAddress([
      'zone' => [
        'territories' => [
          ['country_code' => 'US', 'administrative_area' => 'SC'],
        ],
      ],
    ], 'order_billing_address', ['entity_type' => 'commerce_order', 'profile_scope' => 'billing']);
    $this->assertTrue($condition->evaluate($order));

    $condition = new OrderBillingAddress([
      'zone' => [
        'territories' => [
          ['country_code' => 'US', 'administrative_area' => 'SC'],
        ],
      ],
      'operator' => 'not in',
    ], 'order_billing_address', ['entity_type' => 'commerce_order', 'profile_scope' => 'billing']);
    $this->assertFalse($condition->evaluate($order));
  }

}
