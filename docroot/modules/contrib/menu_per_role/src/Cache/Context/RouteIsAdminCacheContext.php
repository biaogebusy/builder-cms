<?php

declare(strict_types = 1);

namespace Drupal\menu_per_role\Cache\Context;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\Context\CacheContextInterface;
use Drupal\Core\Routing\AdminContext;

/**
 * Defines a cache context depending if the route is an admin route or not.
 */
class RouteIsAdminCacheContext implements CacheContextInterface {

  /**
   * Context ID.
   *
   * @const string
   */
  public const CONTEXT_ID = 'route.is_admin';

  /**
   * The router admin context service.
   *
   * @var \Drupal\Core\Routing\AdminContext
   */
  protected $adminContext;

  /**
   * RouteIsAdminCacheContext constructor.
   *
   * @param \Drupal\Core\Routing\AdminContext $adminContext
   *   The router admin context service.
   */
  public function __construct(
    AdminContext $adminContext
  ) {
    $this->adminContext = $adminContext;
  }

  /**
   * {@inheritdoc}
   */
  public static function getLabel() {
    return \t('Route is admin');
  }

  /**
   * {@inheritdoc}
   */
  public function getContext() {
    return $this->adminContext->isAdminRoute() ? '1' : '0';
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata() {
    return new CacheableMetadata();
  }

}
