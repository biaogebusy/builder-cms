<?php

namespace Drupal\commerce_order\Controller;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class OrderEntityForm extends ControllerBase {

  /**
   * Returns the order form with specified form mode.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $commerce_order
   *   The order entity.
   * @param string $form_mode
   *   The form mode.
   */
  public function form(OrderInterface $commerce_order, string $form_mode): array {
    try {
      return $this->entityFormBuilder()->getForm($commerce_order, $form_mode);
    }
    catch (InvalidPluginDefinitionException) {
      throw new NotFoundHttpException();
    }
  }

  /**
   * Checks access to the page.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $commerce_order
   *   The order entity.
   * @param string $form_mode
   *   The form mode.
   */
  public function access(OrderInterface $commerce_order, string $form_mode): AccessResultInterface {
    switch ($form_mode) {
      case 'add-items':
        $order_item_type_storage = $this->entityTypeManager()->getStorage('commerce_order_item_type');
        $order_item_types = $order_item_type_storage->loadByProperties([
          'orderType' => $commerce_order->bundle(),
        ]);
        return AccessResult::allowedIf(!empty($order_item_types))
          ->addCacheTags(['commerce_order_item_type_list']);

      default:
        // Despite other access checks might return the "allowed" access check
        // if we return "neutral" page will not be accessible.
        return AccessResult::allowed();
    }
  }

}
