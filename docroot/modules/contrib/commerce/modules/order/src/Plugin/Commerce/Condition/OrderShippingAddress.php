<?php

namespace Drupal\commerce_order\Plugin\Commerce\Condition;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\commerce\Attribute\CommerceCondition;

/**
 * Provides the shipping address condition for orders.
 */
#[CommerceCondition(
  id: "order_shipping_address",
  label: new TranslatableMarkup('Shipping address'),
  entity_type: "commerce_order",
  category: new TranslatableMarkup("Customer"),
  weight: 10,
  additional: [
    'profile_scope' => 'shipping',
  ],
)]
class OrderShippingAddress extends CustomerAddressBase {}
