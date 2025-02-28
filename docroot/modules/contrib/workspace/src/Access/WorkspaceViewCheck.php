<?php

namespace Drupal\workspace\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\multiversion\Entity\WorkspaceInterface;

class WorkspaceViewCheck implements AccessInterface {

  /**
   * Checks that the user should be able to view the specified workspace.
   *
   * "View" in practice implies "is allowed to make active".
   *
   * @param WorkspaceInterface $workspace
   *   The workspace to view.
   * @param AccountInterface $account
   *   The user account to check.
   *
   * @return AccessResultInterface
   *   The access result.
   */
  public function access(WorkspaceInterface $workspace, AccountInterface $account) {
    return AccessResult::allowedIf($workspace->access('view', $account))->addCacheableDependency($workspace);
  }
}
