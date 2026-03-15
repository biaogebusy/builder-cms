<?php

namespace Drupal\social_api\AuthManager;

/**
 * Defines an OAuth2Manager Interface.
 *
 * @package Drupal\social_api\OAuth2Manager
 */
interface OAuth2ManagerInterface {

  /**
   * Authenticates the user.
   */
  public function authenticate();

  /**
   * Sets the provider client.
   *
   * @param mixed $client
   *   The service client.
   *
   * @return $this
   *   The current object.
   */
  public function setClient(mixed $client): static;

  /**
   * Gets the service client object.
   *
   * @return mixed
   *   The service client object.
   */
  public function getClient(): mixed;

  /**
   * Gets the access token after authentication.
   *
   * @return \League\OAuth2\Client\Token\AccessToken|mixed
   *   The access token.
   */
  public function getAccessToken(): mixed;

  /**
   * Sets the access token.
   *
   * @param \League\OAuth2\Client\Token\AccessToken|mixed $access_token
   *   The access token.
   *
   * @return $this
   *   The current object.
   */
  public function setAccessToken(mixed $access_token): static;

  /**
   * Returns the authorization URL where user will be redirected.
   *
   * @return string|mixed
   *   Absolute authorization URL.
   */
  public function getAuthorizationUrl(): mixed;

  /**
   * Returns OAuth2 state.
   *
   * @return string
   *   The OAuth2 state.
   */
  public function getState(): string;

  /**
   * Gets data about the user.
   *
   * @return \League\OAuth2\Client\Provider\GenericResourceOwner|array|mixed
   *   User info returned by provider.
   */
  public function getUserInfo(): mixed;

}
