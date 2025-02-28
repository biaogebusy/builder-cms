<?php

namespace Drupal\profile\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityPublishedInterface;
use Drupal\Core\Entity\RevisionLogInterface;
use Drupal\user\EntityOwnerInterface;

/**
 * Provides an interface for profiles.
 */
interface ProfileInterface extends ContentEntityInterface, EntityChangedInterface, EntityOwnerInterface, RevisionLogInterface, EntityPublishedInterface {

  /**
   * Gets whether the profile is active.
   *
   * Unpublished profiles are only visible to their authors and administrators.
   *
   * @deprecated in profile:8.x-1.0-rc4 and is removed from
   *   profile:2.0.0. Use $this->isPublished() instead.
   * @see https://www.drupal.org/node/3044077
   *
   * @return bool
   *   TRUE if the profile is active, FALSE otherwise.
   */
  public function isActive();

  /**
   * Sets whether the profile is active.
   *
   * @param bool $active
   *   Whether the profile is active.
   *
   * @deprecated in profile:8.x-1.0-rc4 and is removed from
   *   profile:2.0.0. Use $this->setPublished() instead.
   * @see https://www.drupal.org/node/3044077
   *
   * @return $this
   */
  public function setActive($active);

  /**
   * Gets whether this is the user's default profile.
   *
   * A user can have a default profile of each type.
   *
   * @return bool
   *   TRUE if this is the user's default profile, FALSE otherwise.
   */
  public function isDefault();

  /**
   * Sets whether this is the user's default profile.
   *
   * @param bool $is_default
   *   Whether this is the user's default profile.
   *
   * @return $this
   */
  public function setDefault($is_default);

  /**
   * Gets a profile data value with the given key.
   *
   * Used to store arbitrary data for the profile.
   *
   * @param string $key
   *   The key.
   * @param mixed $default
   *   The default value.
   *
   * @return mixed
   *   The value.
   */
  public function getData($key, $default = NULL);

  /**
   * Sets a profile data value with the given key.
   *
   * @param string $key
   *   The key.
   * @param mixed $value
   *   The value.
   *
   * @return $this
   */
  public function setData($key, $value);

  /**
   * Unsets a profile data value with the given key.
   *
   * @param string $key
   *   The key.
   *
   * @return $this
   */
  public function unsetData($key);

  /**
   * Gets the profile creation timestamp.
   *
   * @return int
   *   The profile creation timestamp.
   */
  public function getCreatedTime();

  /**
   * Sets the profile creation timestamp.
   *
   * @param int $timestamp
   *   The profile creation timestamp.
   *
   * @return $this
   */
  public function setCreatedTime($timestamp);

  /**
   * Gets the profile revision author.
   *
   * @return \Drupal\user\UserInterface
   *   The user entity for the revision author.
   *
   * @deprecated in profile:8.x-1.0-rc5 and is removed from
   *   profile:2.0.0. Use $this->getRevisionUser() instead.
   * @see https://www.drupal.org/node/2844963
   */
  public function getRevisionAuthor();

  /**
   * Sets the profile revision author.
   *
   * @param int $uid
   *   The user ID of the revision author.
   *
   * @return $this
   *
   * @deprecated in profile:8.x-1.0-rc5 and is removed from
   *   profile:2.0.0. Use $this->setRevisionUserId() instead.
   * @see https://www.drupal.org/node/2844963
   */
  public function setRevisionAuthorId($uid);

  /**
   * Checks whether the other profile is equal to the current profile.
   *
   * By default, profiles are compared using configurable fields only,
   * which means that two profiles can be considered equal even if they
   * are of different types, or belong to different users.
   * Pass "type", and/or "uid" as $field_names to avoid this behavior.
   *
   * @param \Drupal\profile\Entity\ProfileInterface $profile
   *   The other profile.
   * @param string[] $field_names
   *   The names of fields to compare. If empty, all configurable fields
   *   will be compared.
   *
   * @return bool
   *   TRUE if the two profiles are equal, FALSE otherwise.
   */
  public function equalToProfile(ProfileInterface $profile, array $field_names = []);

  /**
   * Populates the profile with field values from the other profile.
   *
   * @param \Drupal\profile\Entity\ProfileInterface $profile
   *   The other profile.
   * @param string[] $field_names
   *   The names of fields to transfer. If empty, all configurable fields
   *   will be transferred.
   *
   * @return $this
   */
  public function populateFromProfile(ProfileInterface $profile, array $field_names = []);

}
