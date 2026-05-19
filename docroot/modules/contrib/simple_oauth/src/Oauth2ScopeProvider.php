<?php

namespace Drupal\simple_oauth;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\simple_oauth\Plugin\ScopeGranularityInterface;
use Drupal\simple_oauth\Plugin\ScopeGranularityRoleInterface;

/**
 * OAuth2 scope provider.
 */
class Oauth2ScopeProvider implements Oauth2ScopeProviderInterface {

  /**
   * The OAuth2 scope adapter.
   *
   * @var \Drupal\simple_oauth\Oauth2ScopeAdapterInterface
   */
  protected Oauth2ScopeAdapterInterface $adapter;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs Oauth2ScopeProvider.
   *
   * @param \Drupal\simple_oauth\Oauth2ScopeAdapterInterface $adapter
   *   The OAuth2 scope adapter.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(Oauth2ScopeAdapterInterface $adapter, EntityTypeManagerInterface $entity_type_manager) {
    $this->adapter = $adapter;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function load(string $id) {
    return $this->adapter->load($id);
  }

  /**
   * {@inheritdoc}
   */
  public function loadMultiple(?array $ids = NULL): array {
    return $this->adapter->loadMultiple($ids);
  }

  /**
   * {@inheritdoc}
   */
  public function loadByName(string $name): ?Oauth2ScopeInterface {
    return $this->adapter->loadByName($name);
  }

  /**
   * {@inheritdoc}
   */
  public function loadMultipleByNames(array $names): array {
    return $this->adapter->loadMultipleByNames($names);
  }

  /**
   * {@inheritdoc}
   */
  public function loadChildren(string $parent_id): array {
    return $this->adapter->loadChildren($parent_id);
  }

  /**
   * {@inheritdoc}
   */
  public function scopeHasPermission(string $permission, Oauth2ScopeInterface $scope): bool {
    if (!$scope->isUmbrella()) {
      $granularity = $scope->getGranularity();
      assert($granularity instanceof ScopeGranularityInterface);
      if ($granularity->hasPermission($permission)) {
        return TRUE;
      }
    }

    $children = $this->loadChildren($scope->id());
    foreach ($children as $child) {
      if ($this->scopeHasPermission($permission, $child)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getRoles(Oauth2ScopeInterface $scope, bool $exclude_locked_roles = FALSE): array {
    $roles = [];
    if ($scope->isUmbrella()) {
      $children = $this->loadChildren($scope->id());
      foreach ($children as $child) {
        $roles = array_merge($roles, $this->getRoles($child, $exclude_locked_roles));
      }
      return array_unique($roles);
    }

    $granularity = $scope->getGranularity();
    if ($granularity instanceof ScopeGranularityRoleInterface) {
      $roles = $granularity->getRoles($exclude_locked_roles);
    }

    return $roles;
  }

  /**
   * {@inheritdoc}
   */
  public function loadByGrantType(string $grant_type): array {
    return $this->adapter->loadByGrantType($grant_type);
  }

  /**
   * {@inheritdoc}
   */
  public function getPermissions(Oauth2ScopeInterface $scope): array {
    if (!$scope->isUmbrella()) {
      $granularity = $scope->getGranularity();
      assert($granularity instanceof ScopeGranularityInterface);
      return $granularity->getPermissions();
    }

    $permissions = [];
    $children = $this->loadChildren($scope->id());
    foreach ($children as $child) {
      $permissions = array_unique(array_merge($permissions, $child->getPermissions()));
    }

    return $permissions;
  }

  /**
   * Adds a permission to the flatten permission tree.
   *
   * @param string $permission
   *   The permission to add.
   * @param array $permissions
   *   The flatten permission tree.
   *
   * @return array
   *   The flatten permission tree.
   */
  private function addPermission(string $permission, array $permissions): array {
    if (!in_array($permission, $permissions)) {
      $permissions[] = $permission;
    }

    return $permissions;
  }

}
