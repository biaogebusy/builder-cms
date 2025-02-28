<?php

namespace Drupal\profile;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\entity\UncacheableEntityAccessControlHandler;
use Drupal\profile\Entity\ProfileType;

/**
 * Defines the access control handler for the profile entity type.
 *
 * Allows profile types to be restricted to specific roles, regardless
 * of permissions. E.g. if a profile type is limited to the role "premium user",
 * and a user doesn't have that role, then not even administrators will
 * see a role for that profile tab on the user's account page.
 */
class ProfileAccessControlHandler extends UncacheableEntityAccessControlHandler {

  /**
   * {@inheritdoc}
   */
  protected function checkCreateAccess(AccountInterface $account, array $context, $entity_bundle = NULL) {
    $result = parent::checkCreateAccess($account, $context, $entity_bundle);
    // Role checks are always done against the profile owner, but it's not safe
    // to assume that $account will be the profile owner.
    // That's why the check is performed only when the profile owner is
    // explicitly provided (e.g. by ProfileFormWidget).
    if ($result->isAllowed() && !empty($context['profile_owner'])) {
      $result = $result->andIf($this->checkRoleAccess($context['profile_owner'], $entity_bundle));
    }

    return $result;
  }

  /**
   * {@inheritdoc}
   */
  protected function checkAccess(EntityInterface $entity, $operation, AccountInterface $account) {
    $result = parent::checkAccess($entity, $operation, $account);
    if ($result->isAllowed()) {
      /** @var \Drupal\profile\Entity\ProfileInterface $entity */
      $owner = $entity->getOwner();
      if ($owner) {
        $result = $result->andIf($this->checkRoleAccess($owner, $entity->bundle()));
      }
    }

    return $result;
  }

  /**
   * Checks whether the account passes the profile type's role requirements.
   *
   * If the profile type has no roles specified, the check will always pass.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The account.
   * @param string $profile_type_id
   *   The profile type ID.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  protected function checkRoleAccess(AccountInterface $account, $profile_type_id) {
    $profile_type = ProfileType::load($profile_type_id);
    $profile_type_roles = array_filter($profile_type->getRoles());
    $role_check = !$profile_type_roles || array_intersect($account->getRoles(), $profile_type_roles);

    return AccessResult::allowedIf($role_check)->addCacheableDependency($profile_type);
  }

}
