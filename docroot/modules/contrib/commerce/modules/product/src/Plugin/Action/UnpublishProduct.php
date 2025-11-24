<?php

namespace Drupal\commerce_product\Plugin\Action;

use Drupal\Core\Action\ActionBase;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Unpublishes a product.
 */
#[Action(
  id: 'commerce_unpublish_product',
  label: new TranslatableMarkup('Unpublish selected product'),
  type: 'commerce_product'
)]
class UnpublishProduct extends ActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    /** @var \Drupal\commerce_product\Entity\ProductInterface $entity */
    $entity->setUnpublished();
    $entity->save();
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    /** @var \Drupal\commerce_product\Entity\ProductInterface $object */
    $access = $object
      ->access('update', $account, TRUE)
      ->andIf($object->status->access('edit', $account, TRUE));

    return $return_as_object ? $access : $access->isAllowed();
  }

}
