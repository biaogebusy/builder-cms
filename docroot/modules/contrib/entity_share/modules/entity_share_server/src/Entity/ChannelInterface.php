<?php

declare(strict_types = 1);

namespace Drupal\entity_share_server\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Url;

/**
 * Provides an interface for defining Channel entities.
 */
interface ChannelInterface extends ConfigEntityInterface {

  /**
   * Permission to access channels list.
   */
  public const CHANNELS_ACCESS_PERMISSION = 'entity_share_server_access_channels';

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

  /**
   * Gets the JSON:API URL for the channel's content.
   *
   * It is the responsibility of the caller to check access.
   *
   * @return \Drupal\Core\Url
   *   The URL for the channel's JSON:API output.
   */
  public function getJsonApiUrl(): Url;

  /**
   * Gets a JSON:API URL for the changed timestamps of the channel's content.
   *
   * @return \Drupal\Core\Url
   *   The URL for the channel's JSON:API UUID output.
   */
  public function getJsonApiChangedTimestampsUrl(): Url;

}
