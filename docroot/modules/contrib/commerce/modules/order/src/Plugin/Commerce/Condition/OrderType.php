<?php

namespace Drupal\commerce_order\Plugin\Commerce\Condition;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\commerce\Attribute\CommerceCondition;
use Drupal\commerce\Plugin\Commerce\Condition\EntityBundleBase;

/**
 * Provides the type condition for orders.
 */
#[CommerceCondition(
  id: "order_type",
  label: new TranslatableMarkup("Order type", [], ["context" => "Commerce"]),
  entity_type: "commerce_order",
  category: new TranslatableMarkup("Order", [], ["context" => "Commerce"]),
)]
class OrderType extends EntityBundleBase {}
