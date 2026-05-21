<?php

namespace Drupal\simple_oauth\Authentication\Provider;

use Drupal\Core\Authentication\AuthenticationProviderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\PermissionCheckerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Path\PathValidatorInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\simple_oauth\Authentication\TokenAuthUser;
use Drupal\simple_oauth\Exception\OAuthUnauthorizedHttpException;
use Drupal\simple_oauth\PageCache\SimpleOauthRequestPolicyInterface;
use Drupal\simple_oauth\Server\ResourceServerFactoryInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * OAuth2 authentication provider.
 *
 * Routes can opt out of OAuth authentication by setting the '_oauth_skip_auth'
 * option to TRUE in their route definition:
 *
 * @code
 * example.route:
 *   path: '/example'
 *   # ...
 *   options:
 *     _oauth_skip_auth: TRUE
 * @endcode
 *
 * @internal
 */
class SimpleOauthAuthenticationProvider implements AuthenticationProviderInterface {

  use StringTranslationTrait;

  /**
   * Constructs an HTTP basic authentication provider object.
   *
   * @param \Drupal\simple_oauth\Server\ResourceServerFactoryInterface $resourceServerFactory
   *   The resource server factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   * @param \Drupal\simple_oauth\PageCache\SimpleOauthRequestPolicyInterface $oauthPageCacheRequestPolicy
   *   The page cache request policy.
   * @param \Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface $httpMessageFactory
   *   The HTTP message factory.
   * @param \Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface $httpFoundationFactory
   *   The HTTP foundation factory.
   * @param \Drupal\Core\Path\PathValidatorInterface $pathValidator
   *   The path validator service.
   * @param \Drupal\Core\Routing\RouteProviderInterface $routeProvider
   *   The route provider service.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   * @param \Drupal\Core\Session\PermissionCheckerInterface $permissionChecker
   *   The permission checker service.
   */
  public function __construct(
    protected readonly ResourceServerFactoryInterface $resourceServerFactory,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly SimpleOauthRequestPolicyInterface $oauthPageCacheRequestPolicy,
    protected readonly HttpMessageFactoryInterface $httpMessageFactory,
    protected readonly HttpFoundationFactoryInterface $httpFoundationFactory,
    protected readonly PathValidatorInterface $pathValidator,
    protected readonly RouteProviderInterface $routeProvider,
    protected readonly RequestStack $requestStack,
    protected readonly PermissionCheckerInterface $permissionChecker,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function applies(Request $request) {
    // Check for OAuth2 request FIRST to avoid unnecessary route lookups. The
    // request policy service won't be used in case of non GET or HEAD methods,
    // so we have to explicitly call it.
    // @see \Drupal\Core\PageCache\RequestPolicy\CommandLineOrUnsafeMethod::check()
    $is_oauth2_request = $this->oauthPageCacheRequestPolicy->isOauth2Request($request);

    // If this is not an OAuth2 request, return FALSE immediately to avoid
    // interfering with other authentication providers (e.g., cookie auth).
    if (!$is_oauth2_request) {
      return FALSE;
    }

    // Only check route options if this IS an OAuth2 request. Retrieve the route
    // name and fetch the route name without access checks.
    try {
      $url_object = $this->pathValidator->getUrlIfValidWithoutAccessCheck($request->getPathInfo());
    }
    catch (\Exception) {
      // The getUrlIfValidWithoutAccessCheck method can throw an exception when
      // using a custom REST resource with limited formats.
      $url_object = NULL;
    }
    if ($url_object) {
      $route_name = $url_object->getRouteName();
      $route = $this->routeProvider->getRouteByName($route_name);
      // Check if the current route has opted out of OAuth authentication.
      if ($route && $route->getOption('_oauth_skip_auth')) {
        return FALSE;
      }
    }

    return TRUE;
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
      // Create a PSR-7 message from the request that is compatible with the
      // OAuth library.
      $psr7_request = $this->httpMessageFactory->createRequest($request);
      $resource_server = $this->resourceServerFactory->get();
      $output_psr7_request = $resource_server->validateAuthenticatedRequest($psr7_request);

      // Convert back to the Drupal/Symfony HttpFoundation objects.
      $auth_request = $this->httpFoundationFactory->createRequest($output_psr7_request);
    }
    catch (OAuthServerException $exception) {
      // Forward authentication challenge to be interpreted by the requester.
      throw new OAuthUnauthorizedHttpException(
        $this->getUnauthorizedExceptionChallenge($request, $exception),
        $exception->getMessage(),
        $exception
      );
    }

    $tokens = $this->entityTypeManager->getStorage('oauth2_token')->loadByProperties([
      'value' => $auth_request->get('oauth_access_token_id'),
    ]);
    $token = reset($tokens);
    $account = new TokenAuthUser(
      $this->permissionChecker,
      $token,
      $this->httpMessageFactory,
      $this->requestStack
    );

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
      throw new OAuthUnauthorizedHttpException(
        $this->getUnauthorizedExceptionChallenge($request, $exception),
        $exception->getMessage(),
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

  /**
   * Formats challenge for unauthorized exception.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request.
   * @param \League\OAuth2\Server\Exception\OAuthServerException $exception
   *   Exception.
   *
   * @return string
   *   Formatted challenge for result.
   */
  protected function getUnauthorizedExceptionChallenge(Request $request, OAuthServerException $exception) {
    return sprintf(
      '%s realm="OAuth", error="%s", error_description="%s"',
      strpos($request->headers->get('Authorization'), 'Bearer') === 0 ? 'Bearer' : 'Basic',
      $exception->getErrorType(),
      $exception->getHint()
    );
  }

}
