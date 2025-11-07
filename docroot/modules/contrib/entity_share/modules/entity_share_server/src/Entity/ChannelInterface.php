<?php

declare(strict_types = 1);

namespace Drupal\entity_share_server\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface for defining Channel entities.
 */
interface ChannelInterface extends ConfigEntityInterface {

  /**
   * Permission to access channels list.
   */
  const CHANNELS_ACCESS_PERMISSION = 'entity_share_server_access_channels';

  /**
   * Remove an authorized role if present. Do not save the entity.
   *
   * @param string $role
   *   The role to remove.
   *
   * @return bool
   *   TRUE if the authorized_roles property has been changed. FALSE otherwise.
   */
  public function removeAuthorizedRole($role);

  /**
   * Remove an authorized user if present. Do not save the entity.
   *
   * @param string $uuid
   *   The uuid of the user to remove.
   *
   * @return bool
   *   TRUE if the authorized_users property has been changed. FALSE otherwise.
   */
  public function removeAuthorizedUser($uuid);

}
