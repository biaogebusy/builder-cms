<?php

namespace Drupal\entity\QueryAccess;

use Drupal\Core\Session\AccountInterface;

/**
 * Controls query access based on the generic entity permissions.
 *
 * @see \Drupal\entity\EntityAccessControlHandler
 * @see \Drupal\entity\EntityPermissionProvider
 */
class QueryAccessHandler extends QueryAccessHandlerBase {

  /**
   * {@inheritdoc}
   */
  protected function buildEntityOwnerConditions($operation, AccountInterface $account) {
    if ($operation == 'view') {
      // EntityPermissionProvider doesn't provide own/any view permissions.
      return $this->buildEntityConditions($operation, $account);
    }

    return parent::buildEntityOwnerConditions($operation, $account);
  }

}
