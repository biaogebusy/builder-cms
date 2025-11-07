<?php

declare(strict_types = 1);

namespace Drupal\entity_share_client\ClientAuthorization;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\Core\Plugin\PluginFormInterface;

/**
 * Defines an interface for Client authorization plugins.
 */
interface ClientAuthorizationInterface extends PluginInspectionInterface, PluginFormInterface, ConfigurableInterface {

  /**
   * The collection ID of for authorization config local storage.
   */
  const LOCAL_STORAGE_KEY_VALUE_COLLECTION = 'entity_share_client.client_authorization';

  /**
   * Gets the plugin label.
   *
   * @return string
   *   The plugin label.
   */
  public function getLabel();

  /**
   * Returns true if the plugin method is supported.
   *
   * The method could be in core, or it could require a contrib module.
   *
   * @return bool
   *   Is this plugin available?
   */
  public function checkIfAvailable();

  /**
   * Prepares a guzzle client for JSON operations with the supported auth.
   *
   * @param string $url
   *   The remote url.
   *
   * @return \GuzzleHttp\Client
   *   The HTTP client.
   */
  public function getJsonApiClient($url);

  /**
   * Prepares a guzzle client for http operations with the supported auth.
   *
   * @param string $url
   *   The url to set in the client.
   *
   * @return \GuzzleHttp\Client
   *   The HTTP client.
   */
  public function getClient($url);

  /**
   * Returns the plugin data if it is set, otherwise returns NULL.
   *
   * @return string|null
   *   The data.
   */
  public function getCredentialProvider();

  /**
   * Returns the plugin data if it is set, otherwise returns NULL.
   *
   * @return mixed|null
   *   The data.
   */
  public function getStorageKey();

}
