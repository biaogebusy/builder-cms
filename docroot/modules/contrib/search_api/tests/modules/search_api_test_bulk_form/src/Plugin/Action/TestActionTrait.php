<?php

namespace Drupal\search_api_test_bulk_form\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Reusable code for test actions.
 */
trait TestActionTrait {

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    return $return_as_object ? AccessResult::allowed() : TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(EntityInterface $entity = NULL) {
    $result = \Drupal::state()->get('search_api_test_bulk_form', []);
    $result[] = [
      $this->getPluginId(),
      $entity->getEntityTypeId(),
      $entity->id(),
    ];
    \Drupal::state()->set('search_api_test_bulk_form', $result);
  }

}
