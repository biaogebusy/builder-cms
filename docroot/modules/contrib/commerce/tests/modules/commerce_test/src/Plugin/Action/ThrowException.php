<?php

namespace Drupal\commerce_test\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Action\ActionBase;
use Drupal\Core\Action\Attribute\Action;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Throws an exception.
 */
#[Action(
  id: 'commerce_test_throw_exception',
  label: new TranslatableMarkup('Throw an exception'),
  category: new TranslatableMarkup('Commerce Test'),
  type: 'commerce_test'
)]
class ThrowException extends ActionBase {

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    return AccessResult::allowed();
  }

  /**
   * {@inheritdoc}
   */
  public function execute() {
    throw new \Exception("Test exception action.");
  }

}
