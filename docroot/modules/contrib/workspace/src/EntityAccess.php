<?php

namespace Drupal\workspace;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\multiversion\Entity\WorkspaceInterface;
use Drupal\multiversion\Workspace\WorkspaceManagerInterface;

/**
 * Service wrapper for hooks relating to entity access control.
 */
class EntityAccess {
  use StringTranslationTrait;

  /**
   * @var int
   *
   * The ID of the default workspace, which has special permission handling.
   */
  protected $defaultWorkspaceId;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\multiversion\Workspace\WorkspaceManagerInterface
   */
  protected $workspaceManager;

  /**
   * Constructs a new EntityAccess.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\multiversion\Workspace\WorkspaceManagerInterface $workspace_manager
   *   The workspace manager service.
   * @param int $default_workspace
   *   The ID of the default workspace.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, WorkspaceManagerInterface $workspace_manager, $default_workspace) {
    $this->entityTypeManager = $entity_type_manager;
    $this->workspaceManager = $workspace_manager;
    $this->defaultWorkspaceId = $default_workspace;
  }

  /**
   * Hook bridge.
   *
   * @see hook_entity_access()
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   * @param string $operation
   * @param \Drupal\Core\Session\AccountInterface $account
   *
   * @return \Drupal\Core\Access\AccessResult
   */
  public function entityAccess(EntityInterface $entity, $operation, AccountInterface $account) {

    // Workspaces themselves are handled by another hook. Ignore them here.
    if ($entity->getEntityTypeId() == 'workspace') {
      return AccessResult::neutral();
    }

    return $this->bypassAccessResult($account);
  }

  /**
   * Hook bridge.
   *
   * @see hook_entity_create_access()
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   * @param array $context
   * @param $entity_bundle
   *
   * @return \Drupal\Core\Access\AccessResult
   */
  public function entityCreateAccess(AccountInterface $account, array $context, $entity_bundle) {

    // Workspaces themselves are handled by another hook. Ignore them here.
    if ($entity_bundle == 'workspace') {
      return AccessResult::neutral();
    }

    return $this->bypassAccessResult($account);
  }

  /**
   * @param \Drupal\Core\Session\AccountInterface $account
   * @return \Drupal\Core\Access\AccessResult
   */
  protected function bypassAccessResult(AccountInterface $account) {
    // This approach assumes that the current "global" active workspace is
    // correct, ie, if you're "in" a given workspace then you get ALL THE PERMS
    // to ALL THE THINGS! That's why this is a dangerous permission.
    $active_workspace = $this->workspaceManager->getActiveWorkspace();

    return AccessResult::allowedIfHasPermission($account, 'bypass_entity_access_workspace_' . $active_workspace->id())
      ->orIf(
        AccessResult::allowedIf($active_workspace->getOwnerId() == $account->id())
          ->andIf(AccessResult::allowedIfHasPermission($account, 'bypass_entity_access_own_workspace'))
      );
  }

  /**
   * Hook bridge.
   *
   * @see hook_entity_access()
   * @see hook_ENTITY_TYPE_access()
   *
   * @param \Drupal\multiversion\Entity\WorkspaceInterface $workspace
   * @param string $operation
   * @param \Drupal\Core\Session\AccountInterface $account
   *
   * @return \Drupal\Core\Access\AccessResult
   */
  public function workspaceAccess(WorkspaceInterface $workspace, $operation, AccountInterface $account) {
    $operations = [
      'view' => ['any' => 'view_any_workspace', 'own' => 'view_own_workspace'],
      'update' => ['any' => 'edit_any_workspace', 'own' => 'edit_own_workspace'],
      'delete' => ['any' => 'delete_any_workspace', 'own' => 'delete_own_workspace'],
    ];
    $route = \Drupal::request()->attributes->get('_route');
    // The default workspace is always viewable, no matter what.
    $result = AccessResult::allowedIf($operation == 'view' && $workspace->id() == $this->defaultWorkspaceId)
      // Or if cron wants to switch workspace.
      ->orIf(AccessResult::allowedIf($route == 'system.cron'))
      ->orIf(AccessResult::allowedIf($route == 'system.run_cron'))
      ->orIf(AccessResult::allowedIf($route == '<none>'))
      // Or if the user has permission to access any workspace at all.
      ->orIf(AccessResult::allowedIfHasPermission($account, $operations[$operation]['any']))
      // Or if it's their own workspace, and they have permission to access their own workspace.
      ->orIf(
        AccessResult::allowedIf($workspace->getOwnerId() == $account->id())
          ->andIf(AccessResult::allowedIfHasPermission($account, $operations[$operation]['own']))
      )
      ->orIf(AccessResult::allowedIfHasPermission($account, $operation . '_workspace_' . $workspace->id()))
      ->orIf(AccessResult::forbiddenIf($operation == 'delete' && (in_array($workspace->id(), [$this->workspaceManager->getActiveWorkspaceId(), $this->defaultWorkspaceId]))));

    return $result;
  }

  /**
   * Hook bridge.
   *
   * @see hook_create_access()
   * @see hook_ENTITY_TYPE_create_access()
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   * @param array $context
   * @param $entity_bundle
   *
   * @return \Drupal\Core\Access\AccessResult
   */
  public function workspaceCreateAccess(AccountInterface $account, array $context, $entity_bundle) {
    return AccessResult::allowedIfHasPermission($account, 'create_workspace');
  }

  /**
   * Returns an array of workspace-specific permissions.
   *
   * Note: This approach assumes that a site will have only a small number
   * of workspace entities, under a dozen. If there are many dozens of
   * workspaces defined then this approach will have scaling issues.
   *
   * @return array
   *   The workspace permissions.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  public function workspacePermissions() {
    $perms = [];

    foreach ($this->getAllWorkspaces() as $workspace) {
      $perms += $this->createWorkspaceViewPermission($workspace)
      + $this->createWorkspaceEditPermission($workspace)
      + $this->createWorkspaceDeletePermission($workspace)
      + $this->createWorkspaceBypassPermission($workspace);
    }

    return $perms;
  }

  /**
   * Returns a list of all workspace entities in the system.
   *
   * @return \Drupal\multiversion\Entity\WorkspaceInterface[]
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  protected function getAllWorkspaces() {
    return $this->entityTypeManager->getStorage('workspace')->loadMultiple();
  }

  /**
   * Derives the view permission for a specific workspace.
   *
   * @param \Drupal\multiversion\Entity\WorkspaceInterface $workspace
   *   The workspace from which to derive the permission.
   *
   * @return array
   *   A single-item array with the permission to define.
   */
  protected function createWorkspaceViewPermission(WorkspaceInterface $workspace) {
    $perms['view_workspace_' . $workspace->id()] = [
      'title' => $this->t('View the %workspace workspace', ['%workspace' => $workspace->label()]),
      'description' => $this->t('View the %workspace workspace and content within it', ['%workspace' => $workspace->label()]),
    ];

    return $perms;
  }

  /**
   * Derives the edit permission for a specific workspace.
   *
   * @param \Drupal\multiversion\Entity\WorkspaceInterface $workspace
   *   The workspace from which to derive the permission.
   *
   * @return array
   *   A single-item array with the permission to define.
   */
  protected function createWorkspaceEditPermission(WorkspaceInterface $workspace) {
    $perms['update_workspace_' . $workspace->id()] = [
      'title' => $this->t('Edit the %workspace workspace', ['%workspace' => $workspace->label()]),
      'description' => $this->t('Edit the %workspace workspace itself', ['%workspace' => $workspace->label()]),
    ];

    return $perms;
  }

  /**
   * Derives the delete permission for a specific workspace.
   *
   * @param \Drupal\multiversion\Entity\WorkspaceInterface $workspace
   *   The workspace from which to derive the permission.
   *
   * @return array
   *   A single-item array with the permission to define.
   */
  protected function createWorkspaceDeletePermission(WorkspaceInterface $workspace) {
    $perms['delete_workspace_' . $workspace->id()] = [
      'title' => $this->t('Delete the %workspace workspace', ['%workspace' => $workspace->label()]),
      'description' => $this->t('View the %workspace workspace and all content within it', ['%workspace' => $workspace->label()]),
    ];

    return $perms;
  }

  /**
   * Derives the delete permission for a specific workspace.
   *
   * @param \Drupal\multiversion\Entity\WorkspaceInterface $workspace
   *   The workspace from which to derive the permission.
   *
   * @return array
   *   A single-item array with the permission to define.
   */
  protected function createWorkspaceBypassPermission(WorkspaceInterface $workspace) {
    $perms['bypass_entity_access_workspace_' . $workspace->id()] = [
      'title' => $this->t('Bypass content entity access in %workspace workspace', ['%workspace' => $workspace->label()]),
      'description' => $this->t('Allow all Edit/Update/Delete permissions for all content in the %workspace workspace', ['%workspace' => $workspace->label()]),
      'restrict access' => TRUE,
    ];

    return $perms;
  }

}
