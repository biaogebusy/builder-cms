<?php

namespace Drupal\profile;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Defines an interface for profile entity storage.
 */
interface ProfileStorageInterface extends EntityStorageInterface {

  /**
   * Loads the given user's profiles.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user.
   * @param string $profile_type_id
   *   The profile type ID.
   * @param bool $published
   *   Whether to load published or unpublished profiles. Defaults to published.
   *
   * @return \Drupal\profile\Entity\ProfileInterface[]
   *   The profiles, ordered by publishing status and ID, descending.
   */
  public function loadMultipleByUser(AccountInterface $account, string $profile_type_id, bool $published = TRUE);

  /**
   * Loads the given user's profile.
   *
   * Takes the default profile, if found.
   * Otherwise falls back to the newest published profile.
   *
   * Primarily used for profile types which only allow a
   * single profile per user.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user.
   * @param string $profile_type_id
   *   The profile type ID.
   *
   * @return \Drupal\profile\Entity\ProfileInterface|null
   *   The profile. NULL if no matching entity was found.
   */
  public function loadByUser(AccountInterface $account, string $profile_type_id);

  /**
   * Loads the given user's default profile.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user.
   * @param string $profile_type_id
   *   The profile type ID.
   *
   * @return \Drupal\profile\Entity\ProfileInterface|null
   *   The profile. NULL if no matching entity was found.
   *
   * @deprecated in profile:8.x-1.0 and is removed from
   *   profile:2.0.0. Use loadByUser() instead.
   * @see https://www.drupal.org/node/3074343
   */
  public function loadDefaultByUser(AccountInterface $account, string $profile_type_id);

}
