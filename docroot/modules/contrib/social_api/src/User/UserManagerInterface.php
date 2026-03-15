<?php

namespace Drupal\social_api\User;

/**
 * Interface for database-related tasks.
 */
interface UserManagerInterface {

  /**
   * Gets the implementer plugin id.
   *
   * @return string
   *   The plugin id.
   */
  public function getPluginId(): string;

  /**
   * Sets the implementer plugin id.
   *
   * @param string $plugin_id
   *   The plugin id.
   */
  public function setPluginId(string $plugin_id): void;

  /**
   * Gets the Drupal user id based on the provider user id.
   *
   * @param string $provider_user_id
   *   User's id on provider.
   *
   * @return int|false
   *   The Drupal user id if it exists.
   *   False otherwise.
   */
  public function getDrupalUserId(string $provider_user_id): int|false;

}
