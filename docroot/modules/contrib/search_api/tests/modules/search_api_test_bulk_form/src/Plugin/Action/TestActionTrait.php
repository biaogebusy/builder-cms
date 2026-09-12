<?php

namespace Drupal\search_api_test_bulk_form\Plugin\Action;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\entity_test\Entity\EntityTest;

/**
 * Reusable code for test actions.
 */
trait TestActionTrait {

  /**
   * {@inheritdoc}
   */
  public function access($object, ?AccountInterface $account = NULL, $return_as_object = FALSE) {
    return $return_as_object ? AccessResult::allowed() : TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function execute(?object $object = NULL) {
    if ($suffix = \Drupal::state()->get('search_api_test_bulk_form.update_name_suffix')) {
      assert($object instanceof EntityTest);
      $object->setName($object->getName() . $suffix)->save();
    }
    $key_value = \Drupal::keyValue('search_api_test');
    $result = $key_value->get('search_api_test_bulk_form', []);
    $result[] = [
      $this->getPluginId(),
      $object->getEntityTypeId(),
      $object->id(),
    ];
    $key_value->set('search_api_test_bulk_form', $result);
  }

}
