<?php

namespace Drupal\views_bulk_operations_test\Plugin\Action;

use Drupal\Core\Messenger\MessengerTrait;
use Drupal\views_bulk_operations\Action\ViewsBulkOperationsActionBase;
use Drupal\Core\Session\AccountInterface;

/**
 * Action for test purposes only.
 *
 * @Action(
 *   id = "views_bulk_operations_simple_test_action",
 *   label = @Translation("VBO simple test action"),
 *   type = "node"
 * )
 */
class ViewsBulkOperationsSimpleTestAction extends ViewsBulkOperationsActionBase {
  use MessengerTrait;

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    $this->messenger()->addMessage(sprintf('Test action (preconfig: %s, label: %s)',
      $this->configuration['preconfig'],
      $entity->label()
    ));
    return 'Test';
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    return $object->access('update', $account, $return_as_object);
  }

}
