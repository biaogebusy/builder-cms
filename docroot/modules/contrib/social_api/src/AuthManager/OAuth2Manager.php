<?php

namespace Drupal\social_api\AuthManager;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Defines basic OAuth2Manager to be used by social auth and social post.
 *
 * @package Drupal\social_api
 */
abstract class OAuth2Manager implements OAuth2ManagerInterface {

  /**
   * The service client.
   *
   * @var \League\OAuth2\Client\Provider\AbstractProvider|mixed
   *
   * @todo Figure out why this is mixed and narrow it.
   */
  protected mixed $client = NULL;

  /**
   * Access token for OAuth2 authentication.
   *
   * @var \League\OAuth2\Client\Token\AccessToken|string|mixed
   *
   * @todo Figure out why this is mixed and narrow it.
   */
  protected mixed $accessToken = NULL;

  /**
   * Social Auth implementer settings.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected ImmutableConfig $settings;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected LoggerChannelFactoryInterface $loggerFactory;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request|null
   */
  protected ?Request $request;

  /**
   * The user returned by the provider.
   *
   * @var \League\OAuth2\Client\Provider\GenericResourceOwner|array|mixed
   *
   * @todo Figure out why this is mixed and narrow it.
   */
  protected mixed $user = NULL;

  /**
   * OAuth2Manager Constructor.
   *
   * @param \Drupal\Core\Config\ImmutableConfig $settings
   *   The implementer settings.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Symfony\Component\HttpFoundation\Request|null $request
   *   The current request.
   */
  public function __construct(ImmutableConfig $settings,
                              LoggerChannelFactoryInterface $logger_factory,
                              Request $request = NULL) {

    $this->settings = $settings;
    $this->loggerFactory = $logger_factory;
    $this->request = $request;
  }

  /**
   * {@inheritdoc}
   */
  public function setClient($client): static {
    $this->client = $client;
    return $this;
  }

  /**
   * {@inheritdoc}
   */
  public function getClient(): mixed {
    return $this->client;
  }

  /**
   * {@inheritdoc}
   */
  public function getAccessToken(): mixed {
    return $this->accessToken;
  }

  /**
   * {@inheritdoc}
   */
  public function setAccessToken($access_token): static {
    $this->accessToken = $access_token;
    return $this;
  }

}
