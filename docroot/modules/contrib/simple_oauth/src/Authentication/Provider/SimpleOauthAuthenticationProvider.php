<?php

namespace Drupal\simple_oauth\Authentication\Provider;

use Drupal\Core\Authentication\AuthenticationProviderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\simple_oauth\Authentication\TokenAuthUser;
use Drupal\simple_oauth\PageCache\SimpleOauthRequestPolicyInterface;
use Drupal\simple_oauth\Server\ResourceServerInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * @internal
 */
class SimpleOauthAuthenticationProvider implements AuthenticationProviderInterface {

  use StringTranslationTrait;

  /**
   * @var \Drupal\simple_oauth\Server\ResourceServerInterface
   */
  protected $resourceServer;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\simple_oauth\PageCache\SimpleOauthRequestPolicyInterface
   */
  protected $oauthPageCacheRequestPolicy;

  /**
   * Constructs a HTTP basic authentication provider object.
   *
   * @param \Drupal\simple_oauth\Server\ResourceServerInterface $resource_server
   *   The resource server object.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\simple_oauth\PageCache\SimpleOauthRequestPolicyInterface $page_cache_request_policy
   *   The page cache request policy.
   */
  public function __construct(
    ResourceServerInterface $resource_server,
    EntityTypeManagerInterface $entity_type_manager,
    SimpleOauthRequestPolicyInterface $page_cache_request_policy
  ) {
    $this->resourceServer = $resource_server;
    $this->entityTypeManager = $entity_type_manager;
    $this->oauthPageCacheRequestPolicy = $page_cache_request_policy;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(Request $request) {
    // The request policy service won't be used in case of non GET or HEAD
    // methods so we have to explicitly call it.
    /* @see \Drupal\Core\PageCache\RequestPolicy\CommandLineOrUnsafeMethod::check() */
    return $this->oauthPageCacheRequestPolicy->isOauth2Request($request);
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \League\OAuth2\Server\Exception\OAuthServerException
   */
  public function authenticate(Request $request) {
    // Update the request with the OAuth information.
    try {
      $auth_request = $this->resourceServer->validateAuthenticatedRequest($request);
    }
    catch (OAuthServerException $exception) {
      // Procedural code here is hard to avoid.
      watchdog_exception('simple_oauth', $exception);

      throw new HttpException(
        $exception->getHttpStatusCode(),
        $exception->getHint(),
        $exception
      );
    }

    $tokens = $this->entityTypeManager->getStorage('oauth2_token')->loadByProperties([
      'value' => $auth_request->get('oauth_access_token_id'),
    ]);
    /** @var \Drupal\simple_oauth\Entity\Oauth2Token $token */
    $token = reset($tokens);

    $account = new TokenAuthUser($token);

    // Revoke the access token for the blocked user.
    if ($account->isBlocked() && $account->isAuthenticated()) {
      $token->revoke();
      $token->save();
      $exception = OAuthServerException::accessDenied(
        $this->t(
          '%name is blocked or has not been activated yet.',
          ['%name' => $account->getAccountName()]
        )
      );
      watchdog_exception('simple_oauth', $exception);
      throw new HttpException(
        $exception->getHttpStatusCode(),
        $exception->getHint(),
        $exception
      );
    }

    // Inherit uploaded files for the current request.
    /* @link https://www.drupal.org/project/drupal/issues/2934486 */
    $request->files->add($auth_request->files->all());
    // Set consumer ID header on successful authentication, so negotiators
    // will trigger correctly.
    $request->headers->set('X-Consumer-ID', $account->getConsumer()->getClientId());

    return $account;
  }

}
