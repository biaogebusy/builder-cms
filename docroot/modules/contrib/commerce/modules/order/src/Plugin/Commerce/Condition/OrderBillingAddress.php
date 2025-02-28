<?php

namespace Drupal\commerce_order\Plugin\Commerce\Condition;

/**
 * Provides the billing address condition for orders.
 *
 * @CommerceCondition(
 *   id = "order_billing_address",
 *   label = @Translation("Billing address"),
 *   category = @Translation("Customer"),
 *   entity_type = "commerce_order",
 *   profile_scope = "billing",
 *   weight = 10,
 * )
 */
class OrderBillingAddress extends CustomerAddressBase {}
