<?php

namespace Drupal\commerce_order\Controller;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Provides title callbacks for order item routes.
 */
class OrderItemController {

  use StringTranslationTrait;

  /**
   * Provides the edit title callback for order items.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The title for the order item edit page.
   */
  public function editTitle(RouteMatchInterface $route_match): TranslatableMarkup {
    $order_item = $route_match->getParameter('commerce_order_item');

    return $this->t('Edit %label', ['%label' => $order_item->label()]);
  }

}
