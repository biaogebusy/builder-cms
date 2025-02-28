<?php

namespace Drupal\profile\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\RevisionableEntityBundleInterface;

/**
 * Provides an interface defining a profile type entity.
 */
interface ProfileTypeInterface extends ConfigEntityInterface, RevisionableEntityBundleInterface {

  /**
   * Gets the profile type display label.
   *
   * This is the user-facing name, unlike the regular label,
   * which is admin-facing. Used on user pages.
   *
   * @return string
   *   The display label. If empty, use the regular label instead.
   */
  public function getDisplayLabel();

  /**
   * Sets the profile type display label.
   *
   * @param string $display_label
   *   The display label.
   *
   * @return $this
   */
  public function setDisplayLabel($display_label);

  /**
   * Gets whether a user can have multiple profiles of this type.
   *
   * @return bool
   *   TRUE if a user can have multiple profiles of this type, FALSE otherwise.
   */
  public function allowsMultiple();

  /**
   * Sets whether a user can have multiple profiles of this type.
   *
   * @param bool $multiple
   *   Whether a user can have multiple profiles of this type.
   *
   * @return $this
   */
  public function setMultiple($multiple);

  /**
   * Gets whether a profile of this type should be created during registration.
   *
   * @return bool
   *   TRUE a profile of this type should be created during registration,
   *   FALSE otherwise.
   */
  public function getRegistration();

  /**
   * Sets whether a profile of this type should be created during registration.
   *
   * @param bool $registration
   *   Whether a profile of this type should be created during registration.
   *
   * @return $this
   */
  public function setRegistration($registration);

  /**
   * Gets the user roles allowed to have profiles of this type.
   *
   * @return string[]
   *   The role IDs. If empty, all roles are allowed.
   */
  public function getRoles();

  /**
   * Sets the user roles allowed to have profiles of this type.
   *
   * @param string[] $rids
   *   The role IDs.
   *
   * @return $this
   */
  public function setRoles(array $rids);

  /**
   * Gets whether this profile type allows revisions.
   *
   * @return bool
   *   Whether this profile type allows revisions.
   */
  public function allowsRevisions();

  /**
   * Gets whether profiles of this type should show the revision fields.
   *
   * @return bool
   *   Whether profiles of this type should show the revision fields.
   */
  public function showRevisionUi();

}
