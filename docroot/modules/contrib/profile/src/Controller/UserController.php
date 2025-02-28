<?php

namespace Drupal\profile\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\profile\Entity\ProfileInterface;
use Drupal\profile\Entity\ProfileTypeInterface;
use Drupal\user\UserInterface;

/**
 * Provides the profile UI for users.
 */
class UserController extends ControllerBase {

  /**
   * Builds a page title for the given profile type.
   *
   * @param \Drupal\profile\Entity\ProfileTypeInterface $profile_type
   *   The profile type.
   *
   * @return string
   *   The page title.
   */
  public function title(ProfileTypeInterface $profile_type) {
    return $profile_type->getDisplayLabel() ?: $profile_type->label();
  }

  /**
   * Builds the add/edit page for "single" profile types.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user account.
   * @param \Drupal\profile\Entity\ProfileTypeInterface $profile_type
   *   The profile type.
   *
   * @return array
   *   The response.
   */
  public function singlePage(UserInterface $user, ProfileTypeInterface $profile_type) {
    /** @var \Drupal\profile\ProfileStorageInterface $profile_storage */
    $profile_storage = $this->entityTypeManager()->getStorage('profile');
    $profile = $profile_storage->loadByUser($user, $profile_type->id());

    if ($profile) {
      return $this->editForm($profile);
    }
    else {
      return $this->addForm($user, $profile_type);
    }
  }

  /**
   * Builds the listing page for "multiple" profile types.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user account.
   * @param \Drupal\profile\Entity\ProfileTypeInterface $profile_type
   *   The profile type.
   *
   * @return array
   *   The response.
   */
  public function multiplePage(UserInterface $user, ProfileTypeInterface $profile_type) {
    $build = [];
    $build['profiles'] = [
      '#type' => 'view',
      '#name' => 'profiles',
      '#display_id' => 'user_page',
      '#arguments' => [$user->id(), $profile_type->id(), 1],
      '#embed' => TRUE,
    ];

    return $build;
  }

  /**
   * Builds the profile add form.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user account.
   * @param \Drupal\profile\Entity\ProfileTypeInterface $profile_type
   *   The profile type.
   *
   * @return array
   *   The response.
   */
  public function addForm(UserInterface $user, ProfileTypeInterface $profile_type) {
    $profile = $this->entityTypeManager()->getStorage('profile')->create([
      'uid' => $user->id(),
      'type' => $profile_type->id(),
    ]);
    return $this->entityFormBuilder()->getForm($profile, 'add');
  }

  /**
   * Builds the edit form.
   *
   * @param \Drupal\profile\Entity\ProfileInterface $profile
   *   The profile.
   *
   * @return array
   *   The response.
   */
  public function editForm(ProfileInterface $profile) {
    return $this->entityFormBuilder()->getForm($profile, 'edit');
  }

  /**
   * Checks access for the single/multiple pages.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user account.
   * @param \Drupal\profile\Entity\ProfileTypeInterface $profile_type
   *   The profile type.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function checkAccess(UserInterface $user, ProfileTypeInterface $profile_type, AccountInterface $account) {
    $user_access = $user->access('view', $account, TRUE);
    if (!$user_access->isAllowed()) {
      // The account does not have access to the user's canonical page
      // ("/user/{user}"), don't allow access to any sub-pages either.
      return $user_access;
    }

    $access_control_handler = $this->entityTypeManager()->getAccessControlHandler('profile');
    $profile_storage = $this->entityTypeManager()->getStorage('profile');
    $profile_stub = $profile_storage->create([
      'type' => $profile_type->id(),
      'uid' => $user->id(),
    ]);
    $operation = $profile_type->allowsMultiple() ? 'view' : 'update';
    $result = $access_control_handler->access($profile_stub, $operation, $account, TRUE);

    return $result;
  }

  /**
   * Checks access for the profile add form.
   *
   * @param \Drupal\user\UserInterface $user
   *   The user account.
   * @param \Drupal\profile\Entity\ProfileTypeInterface $profile_type
   *   The profile type.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The currently logged in account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function checkCreateAccess(UserInterface $user, ProfileTypeInterface $profile_type, AccountInterface $account) {
    $user_access = $user->access('view', $account, TRUE);
    if (!$user_access->isAllowed()) {
      // The account does not have access to the user's canonical page
      // ("/user/{user}"), don't allow access to any sub-pages either.
      return $user_access;
    }

    $access_control_handler = $this->entityTypeManager()->getAccessControlHandler('profile');
    /** @var \Drupal\Core\Access\AccessResult $result */
    $result = $access_control_handler->createAccess($profile_type->id(), $account, [
      'profile_owner' => $user,
    ], TRUE);
    if ($result->isAllowed()) {
      // There is no create any/own permission, confirm that the account is
      // either an administrator, or they're creating a profile for themselves.
      $admin_permission = $this->entityTypeManager()->getDefinition('profile')->getAdminPermission();
      $owner_result = AccessResult::allowedIfHasPermission($account, $admin_permission)
        ->orIf(AccessResult::allowedIf($account->id() == $user->id()))
        ->cachePerUser();
      $result = $result->andIf($owner_result);
    }

    return $result;
  }

}
