<?php

declare(strict_types = 1);

namespace Drupal\menu_per_role;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Menu\DefaultMenuLinkTreeManipulators;
use Drupal\Core\Menu\MenuLinkInterface;
use Drupal\Core\Routing\AdminContext;
use Drupal\menu_link_content\Plugin\Menu\MenuLinkContent;

/**
 * Menu Per Role link tree manipulator service.
 */
class MenuPerRoleLinkTreeManipulator extends DefaultMenuLinkTreeManipulators {

  /**
   * The router admin context service.
   *
   * @var \Drupal\Core\Routing\AdminContext
   */
  protected $adminContext;

  /**
   * The config service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $config;

  /**
   * The list of the admin roles.
   *
   * @var string[]
   */
  protected $adminRoles;

  /**
   * Sets the admin context.
   *
   * @param \Drupal\Core\Routing\AdminContext $adminContext
   *   The router admin context service.
   */
  public function setAdminContext(AdminContext $adminContext): void {
    $this->adminContext = $adminContext;
  }

  /**
   * Sets the config service.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config
   *   The config service.
   */
  public function setConfigFactory(ConfigFactoryInterface $config): void {
    $this->config = $config;
  }

  /**
   * {@inheritdoc}
   */
  protected function menuLinkCheckAccess(MenuLinkInterface $instance) {
    $result = parent::menuLinkCheckAccess($instance);
    $cache_contexts = [
      'user.is_super_user',
      'route.is_admin',
    ];

    if ($this->bypassAccessCheck()) {
      $result->andIf(AccessResult::neutral()->addCacheContexts($cache_contexts));
      return $result;
    }

    if ($instance instanceof MenuLinkContent) {
      // Sadly ::getEntity() is protected at the moment.
      $function = function () {
        // @phpstan-ignore-next-line
        return $this->getEntity();
      };
      $function = \Closure::bind($function, $instance, \get_class($instance));
      /** @var \Drupal\menu_link_content\Entity\MenuLinkContent $entity */
      $entity = $function();
      if (isset($entity->menu_per_role__show_role)) {
        /** @var array $show_role */
        $show_role = $entity->menu_per_role__show_role->getValue();
        $show_role = \array_column($show_role, 'target_id');
        /** @var array $hidden_role */
        $hidden_role = $entity->menu_per_role__hide_role->getValue();
        $hidden_role = \array_column($hidden_role, 'target_id');

        // Check whether this role has visibility access (must be present).
        if ($show_role && \count(\array_intersect($show_role, $this->account->getRoles())) == 0) {
          $result = $result->andIf(AccessResult::forbidden()
            ->addCacheContexts(['user.roles']));
        }

        // Check whether this role has visibility access (must not be present).
        if ($hidden_role && \count(\array_intersect($hidden_role, $this->account->getRoles())) > 0) {
          $result = $result->andIf(AccessResult::forbidden()
            ->addCacheContexts(['user.roles']));
        }
      }
    }
    return $result;
  }

  /**
   * Check if the user can bypass the access check.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *
   * @return bool
   *   TRUE if the Menu Per Role access check should be bypassed.
   */
  protected function bypassAccessCheck(): bool {
    $bypass_access_check = FALSE;
    $menu_per_role_settings = $this->config->get('menu_per_role.settings');
    $admin_bypass_access_front = $menu_per_role_settings->get('admin_bypass_access_front');
    $admin_bypass_access_admin = $menu_per_role_settings->get('admin_bypass_access_admin');
    $context_is_admin = $this->adminContext->isAdminRoute();
    $user_is_admin = $this->isUserAdmin();

    // Admin user access check bypass.
    if ($user_is_admin) {
      if (
        ($context_is_admin && $admin_bypass_access_admin)
        || (!$context_is_admin && $admin_bypass_access_front)
      ) {
        $bypass_access_check = TRUE;
      }
    }
    // Normal user access check bypass.
    else {
      if (
        ($context_is_admin && $this->account->hasPermission('bypass menu_per_role access admin'))
        || (!$context_is_admin && $this->account->hasPermission('bypass menu_per_role access front'))
      ) {
        $bypass_access_check = TRUE;
      }
    }

    return $bypass_access_check;
  }

  /**
   * Check if the current user is admin. Either due to uid 1 or admin roles.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *
   * @return bool
   *   TRUE if the user admin. FALSE otherwise.
   */
  protected function isUserAdmin(): bool {
    if ($this->account->id() == 1) {
      return TRUE;
    }

    // Get admin roles only one time.
    if (!$this->adminRoles) {
      /** @var \Drupal\user\RoleStorageInterface $role_storage */
      $role_storage = $this->entityTypeManager->getStorage('user_role');
      /** @var string[] $admin_roles */
      $admin_roles = $role_storage->getQuery()
        ->condition('is_admin', TRUE)
        ->accessCheck(FALSE)
        ->execute();
      $this->adminRoles = $admin_roles;
    }

    $account_roles = $this->account->getRoles(TRUE);
    foreach ($account_roles as $account_role) {
      if (\in_array($account_role, $this->adminRoles, TRUE)) {
        return TRUE;
      }
    }

    return FALSE;
  }

}
