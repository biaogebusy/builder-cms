<?php

namespace Drupal\profile\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\profile\Entity\ProfileTypeInterface;
use Symfony\Component\Routing\Route;

/**
 * Checks whether the profile type allows multiple profiles per user.
 *
 * Requirements key: '_profile_type_multiple'.
 */
class ProfileTypeMultipleAccessCheck implements AccessInterface {

  /**
   * Performs the access check.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route to check against.
   * @param \Drupal\profile\Entity\ProfileTypeInterface $profile_type
   *   The profile type.
   *
   * @return bool|\Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(Route $route, ProfileTypeInterface $profile_type) {
    $requirement = $route->getRequirement('_profile_type_multiple');
    $requirement = filter_var($requirement, FILTER_VALIDATE_BOOLEAN);
    if ($requirement) {
      $access_result = AccessResult::allowedIf($profile_type->allowsMultiple());
    }
    else {
      $access_result = AccessResult::allowedIf(!$profile_type->allowsMultiple());
    }

    return $access_result->addCacheableDependency($profile_type);
  }

}
