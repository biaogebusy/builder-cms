<?php

namespace Drupal\social_auth\AuthManager;

use Drupal\social_api\AuthManager\OAuth2ManagerInterface as BaseOAuth2ManagerInterface;

/**
 * Defines an OAuth2Manager Interface.
 *
 * @package Drupal\social_auth\AuthManager
 */
interface OAuth2ManagerInterface extends BaseOAuth2ManagerInterface {

  /**
   * Request data from the declared endpoints.
   *
   * @param string $method
   *   The HTTP method for the request.
   * @param string|null $domain
   *   The domain to request.
   *
   * @return array|null
   *   The extra details gotten from provider.
   */
  public function getExtraDetails(string $method = 'GET', ?string $domain = NULL): ?array;

  /**
   * Request and end point.
   *
   * @param string $method
   *   The HTTP method for the request.
   * @param string $path
   *   The path to request.
   * @param string|null $domain
   *   The domain to request.
   * @param array $options
   *   Request options.
   *
   * @return array|mixed
   *   Data returned by provider.
   */
  public function requestEndPoint(string $method, string $path, ?string $domain = NULL, array $options = []): mixed;

  /**
   * Gets the scopes defined in the settings form.
   *
   * @return string
   *   Data points separated by comma.
   */
  public function getScopes(): string;

  /**
   * Gets the API endpoints to be requested.
   *
   * @return string
   *   API endpoints separated in different lines.
   */
  public function getEndPoints(): string;

}
