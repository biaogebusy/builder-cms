<?php

namespace Drupal\panels\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface as CoreAccessInterface;
use Drupal\Core\Routing\RouteMatch;
use Drupal\Core\Session\AccountInterface;
use Drupal\ctools\Access\AccessInterface as CToolsAccessInterface;
use Drupal\Core\TempStore\SharedTempStoreFactory;
use Drupal\panels\CachedValuesGetterTrait;
use Symfony\Component\Routing\Route;

/**
 * Temporary storage access check.
 *
 * @see \Drupal\ctools\Access\TempstoreAccess
 */
class TempstoreAccess implements CoreAccessInterface {

  use CachedValuesGetterTrait;

  /**
   * The shared tempstore factory.
   *
   * @var \Drupal\Core\TempStore\SharedTempStoreFactory
   */
  protected $tempstore;

  /**
   * TempstoreAccess constructor.
   *
   * @param SharedTempStoreFactory $tempstore
   *   The shared tempstore factory.
   */
  public function __construct(SharedTempStoreFactory $tempstore) {
    $this->tempstore = $tempstore;
  }

  /**
   * Access check.
   *
   * @param Route $route
   *   The route.
   * @param RouteMatch $match
   *   The route match.
   * @param AccountInterface $account
   *   The user account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(Route $route, RouteMatch $match, AccountInterface $account) {
    $tempstore_id = $match->getParameter('tempstore_id')
      ? $match->getParameter('tempstore_id')
      : $route->getDefault('tempstore_id');
    $id = $match->getParameter($route->getRequirement('_panels_tempstore_access'));
    if ($tempstore_id && $id) {
      $cached_values = $this->getCachedValues($this->tempstore, $tempstore_id, $id);
      if (!empty($cached_values['access']) && ($cached_values['access'] instanceof CToolsAccessInterface)) {
        $access = $cached_values['access']->access($account);
      }
      else {
        $access = AccessResult::allowed();
      }
    }
    else {
      $access = AccessResult::forbidden();
    }

    // The different wizards will have different tempstore ids and adding this
    // cache context allows us to nuance the access per wizard.
    $access->addCacheContexts(['url.query_args:tempstore_id']);
    return $access;
  }

}
