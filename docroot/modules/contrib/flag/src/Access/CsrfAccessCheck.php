<?php

namespace Drupal\flag\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\CsrfAccessCheck as OrignalCsrfAccessCheck;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Symfony\Component\Routing\Route;
use Symfony\Component\HttpFoundation\Request;

/**
 * Proxy class to the core CSRF access chcker allowing anonymous requests.
 *
 * As per https://www.drupal.org/node/2319205 this is OK and desired.
 */
class CsrfAccessCheck implements AccessInterface {

  /**
   * @var \Drupal\Core\Access\CsrfAccessCheck
   */
  protected $original;

  /**
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $account;

  /**
   * CsrfAccessCheck constructor.
   *
   * @param \Drupal\Core\Access\CsrfAccessCheck $original
   */
  public function __construct(OrignalCsrfAccessCheck $original, AccountInterface $account) {
    $this->original = $original;
    $this->account = $account;
  }

  /**
   * Checks access based on a CSRF token for the request for auth users.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route to check against.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match object.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result, always allowed for anonymous users.
   */
  public function access(Route $route, Request $request, RouteMatchInterface $route_match) {
    // As the original returns AccessResult::allowedif the token validates,
    // we do the same for anonymous.
    return $this->account->isAnonymous() ? AccessResult::allowed() : $this->original->access($route, $request, $route_match);
  }

}
