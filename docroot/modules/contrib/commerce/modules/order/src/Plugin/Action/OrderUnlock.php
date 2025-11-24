<?php

namespace Drupal\commerce_order\Plugin\Action;

use Drupal\Core\Action\ActionBase;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Unlock order.
 */
#[Action(
  id: 'commerce_order_unlock',
  label: new TranslatableMarkup('Unlock'),
  type: 'commerce_order'
)]
class OrderUnlock extends ActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $entity */
    $entity->unlock();
    $entity->save();
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    return $object->access('unlock', $account, $return_as_object);
  }

}
