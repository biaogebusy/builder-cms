<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\entity_share_client\ClientAuthorization\ClientAuthorizationInterface;

/**
 * Provides an interface for defining Remote entities.
 */
interface RemoteInterface extends ConfigEntityInterface {

  /**
   * Copies plugin specific data into the Remote.
   *
   * @param \Drupal\entity_share_client\ClientAuthorization\ClientAuthorizationInterface $plugin
   *   The authorization plugin to merge.
   */
  public function mergePluginConfig(ClientAuthorizationInterface $plugin);

  /**
   * Helper method to instantiate auth plugin from this entity.
   *
   * @return \Drupal\entity_share_client\ClientAuthorization\ClientAuthorizationInterface|null
   *   The plugin if it is defined.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginException
   */
  public function getAuthPlugin();

  /**
   * Prepares a client object with options pulled from the auth plugin.
   *
   * @param bool $json
   *   Is this client for JSON operations?
   *
   * @return \GuzzleHttp\Client
   *   The configured client.
   */
  public function getHttpClient(bool $json);

}
