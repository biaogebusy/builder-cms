<?php

namespace Drupal\commerce_order\Plugin\Commerce\Condition;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\commerce\Attribute\CommerceCondition;

/**
 * Provides the billing address condition for orders.
 */
#[CommerceCondition(
  id: "order_billing_address",
  label: new TranslatableMarkup('Billing address'),
  entity_type: "commerce_order",
  category: new TranslatableMarkup("Customer"),
  weight: 10,
  additional: [
    'profile_scope' => 'billing',
  ],
)]
class OrderBillingAddress extends CustomerAddressBase {}
