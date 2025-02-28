<?php

namespace Drupal\quick_node_clone\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\Entity\Node;

/**
 * Access control for cloning nodes.
 */
class QuickNodeCloneNodeAccess {

  /**
   * Limit access to the clone according to their restricted state.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account object.
   * @param int $node
   *   The node id.
   *
   * @return \Drupal\Core\Access\AccessResultAllowed|\Drupal\Core\Access\AccessResultForbidden
   *   If allowed, AccessResultAllowed isAllowed() will be TRUE. If forbidden,
   *   isForbidden() will be TRUE.
   */
  public function cloneNode(AccountInterface $account, $node) {
    $node = Node::load($node);

    if (_quick_node_clone_has_clone_permission($node)) {
      $result = AccessResult::allowed();
    }
    else {
      $result = AccessResult::forbidden();
    }

    $result->addCacheableDependency($node);

    return $result;
  }

}
