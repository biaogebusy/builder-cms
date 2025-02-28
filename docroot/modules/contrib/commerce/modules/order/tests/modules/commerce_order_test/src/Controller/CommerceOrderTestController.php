<?php

namespace Drupal\commerce_order_test\Controller;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\Controller\ControllerBase;

/**
 * Test controller.
 */
class CommerceOrderTestController extends ControllerBase {

  /**
   * Attempts to save the commerce order without a lock.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $commerce_order
   *   The order entity.
   */
  public function testSaveNoLock(OrderInterface $commerce_order) {
    try {
      $commerce_order->setData('conflicting_update', 'successful');
      $commerce_order->save();

      return ['#markup' => $this->t('Saved the order successfully')];
    }
    catch (\Exception $e) {
      return ['#markup' => $e->getMessage()];
    }
  }

  /**
   * Attempts to save the commerce order with a lock.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $commerce_order
   *   The order entity.
   */
  public function testSaveLock(OrderInterface $commerce_order) {
    try {
      $commerce_order = $this->entityTypeManager()->getStorage('commerce_order')->loadForUpdate($commerce_order->id());
      $commerce_order->setData('second_update', 'successful');
      $commerce_order->save();

      return ['#markup' => $this->t('Saved the order successfully')];
    }
    catch (\Exception $e) {
      return ['#markup' => $e->getMessage()];
    }
  }

}
