<?php

namespace Drupal\lightning_roles;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\user\PermissionHandlerInterface;

/**
 * A service for managing the configuration and deployment of content roles.
 */
class ContentRoleManager {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The node type entity storage handler.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $nodeTypeStorage;

  /**
   * The permission handler service.
   *
   * @var \Drupal\user\PermissionHandlerInterface
   */
  protected $permissionHandler;

  /**
   * ContentRoleManager constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\user\PermissionHandlerInterface $permission_handler
   *   The permission handler service.
   */
  public function __construct(ConfigFactoryInterface $config_factory, EntityTypeManagerInterface $entity_type_manager, PermissionHandlerInterface $permission_handler = NULL) {
    $this->configFactory = $config_factory;
    $this->nodeTypeStorage = $entity_type_manager->getStorage('node_type');
    $this->permissionHandler = $permission_handler ?: \Drupal::service('user.permissions');
  }

  /**
   * Grants permissions (or meta-permissions) to a content role.
   *
   * @param string $role_id
   *   The content role ID.
   * @param string[] $permissions
   *   The permissions to grant. Can contain the '?' token, which will be
   *   replaced with the node type ID.
   *
   * @return $this
   *   The called object, for chaining.
   */
  public function grantPermissions($role_id, array $permissions) {
    $key = "content_roles.{$role_id}";

    $config = $this->configFactory->getEditable('lightning_roles.settings');

    // Add the raw permissions to the content role.
    $role = $config->get($key);
    $role['permissions'] = array_merge($role['permissions'], $permissions);
    $config->set($key, $role)->save();

    $all_permissions = array_keys($this->permissionHandler->getPermissions());

    if ($role['enabled']) {
      // Look up all node type IDs.
      $node_types = $this->nodeTypeStorage->getQuery()->execute();

      foreach ($node_types as $node_type) {
        $permissions = str_replace('?', $node_type, $role['permissions']);
        // Filter out any undefined permissions.
        $permissions = array_intersect($permissions, $all_permissions);

        user_role_grant_permissions($node_type . '_' . $role_id, $permissions);
      }
    }
    return $this;
  }

}
