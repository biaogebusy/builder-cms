<?php

namespace Drupal\commerce_order\Plugin\Commerce\Condition;

/**
 * Provides the shipping address condition for orders.
 *
 * @CommerceCondition(
 *   id = "order_shipping_address",
 *   label = @Translation("Shipping address"),
 *   category = @Translation("Customer"),
 *   entity_type = "commerce_order",
 *   profile_scope = "shipping",
 *   weight = 10,
 * )
 */
class OrderShippingAddress extends CustomerAddressBase {}
