<?php

namespace Drupal\social_auth_google;

use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Utility\Error;
use Drupal\social_auth\AuthManager\OAuth2Manager;
use Drupal\social_auth\User\SocialAuthUser;
use Drupal\social_auth\User\SocialAuthUserInterface;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Contains all the logic for Google OAuth2 authentication.
 */
class GoogleAuthManager extends OAuth2Manager {

  /**
   * GoogleAuthManager constructor.
   */
  public function __construct(
    ConfigFactory $configFactory,
    LoggerChannelFactoryInterface $logger_factory,
    RequestStack $request_stack,
  ) {
    parent::__construct(
      $configFactory->get('social_auth_google.settings'),
      $logger_factory,
      $request_stack->getCurrentRequest(),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function authenticate(): void {
    if ($code = $this->request->query->get('code')) {
      try {
        $this->setAccessToken($this->client->getAccessToken('authorization_code', ['code' => $code]));
      }
      catch (\Throwable $e) {
        $this->loggerFactory->get('social_auth_google')->error('There was an error during authentication. ' . Error::DEFAULT_ERROR_MESSAGE . ' @backtrace_string', Error::decodeException($e));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function getUserInfo(): SocialAuthUserInterface {
    if (!$this->user && $access_token = $this->getAccessToken()) {
      /** @var \League\OAuth2\Client\Provider\GoogleUser $owner */
      $owner = $this->client->getResourceOwner($access_token);
      $this->user = new SocialAuthUser(
        $owner->getName(),
        $owner->getId(),
        $this->getAccessToken(),
        $owner->getEmail(),
        $owner->getAvatar(),
        $this->getExtraDetails()
      );
      $this->user->setFirstName($owner->getFirstName());
      $this->user->setLastName($owner->getLastName());
    }
    return $this->user;
  }

  /**
   * {@inheritdoc}
   */
  public function getAuthorizationUrl(): string {
    $scopes = [
      'email',
      'profile',
    ];

    $extra_scopes = $this->getScopes();
    if ($extra_scopes) {
      $scopes = array_merge($scopes, explode(',', $extra_scopes));
    }

    // Returns the URL where user will be redirected.
    return $this->client->getAuthorizationUrl([
      'scope' => $scopes,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function requestEndPoint(string $method, string $path, ?string $domain = NULL, array $options = []): mixed {
    if (!$domain) {
      $domain = 'https://www.googleapis.com';
    }

    $url = $domain . $path;

    $request = $this->client->getAuthenticatedRequest($method, $url, $this->getAccessToken(), $options);

    try {
      return $this->client->getParsedResponse($request);
    }
    catch (IdentityProviderException $e) {
      $this->loggerFactory->get('social_auth_google')
        ->error('There was an error when requesting ' . $url . '. Exception: ' . $e->getMessage());
    }

    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getState(): string {
    return $this->client->getState();
  }

}
