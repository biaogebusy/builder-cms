<?php

namespace Drupal\page_manager\Entity;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\Routing\Route;

/**
 * Mimics the generic entity access but with a custom key to prevent collisions.
 */
class PageAccessCheck implements AccessInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Creates a new PageAccessCheck.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   */
  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Checks access to the page operation on the given route.
   *
   * We can not use _entity_access as route match can not see params that
   * start with '_'.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route to check against.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The parametrized route.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in account.
   *
   * @see https://www.drupal.org/project/drupal/issues/3277784
   * @see https://www.drupal.org/project/page_manager/issues/3362561
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(Route $route, RouteMatchInterface $route_match, AccountInterface $account) {
    // Try to load the page manager page from the route defaults.
    $pageId = $route->getDefault('_page_manager_page');
    if (!empty($pageId)) {
      try {
        $page = $this->entityTypeManager->getStorage('page')->load($pageId);
      }
      catch (\Exception $e) {
        // Could not load the entity, we fall back to the default behaviour.
      }
    }

    // If we could load a page, perform the 'view' access check on it.
    // We always check the 'view' access, because that is the only operation
    // for which we add this access check.
    if (isset($page) && $page instanceof EntityInterface) {
      return $page->access('view', $account, TRUE);
    }

    // If no page could be found or loaded, we have no opinion, so other
    // access checks should decide if access should be allowed or not.
    return AccessResult::neutral();
  }

}
