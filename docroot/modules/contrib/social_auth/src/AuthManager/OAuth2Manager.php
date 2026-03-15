<?php

namespace Drupal\social_auth\AuthManager;

use Drupal\social_api\AuthManager\OAuth2Manager as BaseOAuth2Manager;

/**
 * Defines a basic OAuth2Manager.
 *
 * @package Drupal\social_auth
 */
abstract class OAuth2Manager extends BaseOAuth2Manager implements OAuth2ManagerInterface {

  /**
   * The scopes to be requested.
   *
   * @var string|null
   */
  protected ?string $scopes = NULL;

  /**
   * The end points to be requested.
   *
   * @var string|null
   */
  protected ?string $endPoints = NULL;

  /**
   * The user returned by the provider.
   *
   * @var mixed
   */
  protected mixed $user = NULL;

  /**
   * {@inheritdoc}
   */
  public function getExtraDetails(string $method = 'GET', ?string $domain = NULL): ?array {
    $endpoints = $this->getEndPoints();

    // Stores the data mapped with endpoints define in settings.
    $data = [];

    if ($endpoints) {
      // Iterates through endpoints define in settings and retrieves them.
      foreach (explode(PHP_EOL, $endpoints) as $endpoint) {
        // Endpoint is set as path/to/endpoint|name.
        $parts = explode('|', $endpoint);

        $data[$parts[1]] = $this->requestEndPoint($method, $parts[0], $domain);
      }

      return $data;
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getScopes(): string {
    if ($this->scopes === NULL) {
      $this->scopes = $this->settings->get('scopes');
    }

    return $this->scopes;
  }

  /**
   * {@inheritdoc}
   */
  public function getEndPoints(): string {
    if ($this->endPoints === NULL) {
      $this->endPoints = $this->settings->get('endpoints');
    }

    return $this->endPoints;
  }

}
