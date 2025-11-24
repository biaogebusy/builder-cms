<?php

namespace Drupal\page_manager_ui\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\ctools\Access\AccessInterface;

/**
 * Access Plugin for Page Manager Plugins.
 */
class PageManagerPluginAccess implements AccessInterface {

  /**
   * Account Interface public method.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Account to check access on.
   *
   * @return \Drupal\Core\Access\AccessResultAllowed|\Drupal\Core\Access\AccessResultForbidden
   *   The Page Manager plugin access result.
   */
  public function access(AccountInterface $account) {
    return $account->hasPermission('administer pages') ? AccessResult::allowed() : AccessResult::forbidden();
  }

}
