<?php

namespace Drupal\simple_oauth\Controller;

use Drupal\Component\Utility\UrlHelper;
use Drupal\consumers\Entity\ConsumerInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\simple_oauth\Entities\ScopeEntity;
use Drupal\simple_oauth\Entities\UserEntity;
use Drupal\simple_oauth\Form\Oauth2AuthorizeForm;
use Drupal\simple_oauth\KnownClientsRepositoryInterface;
use Drupal\simple_oauth\Oauth2ScopeProviderInterface;
use Drupal\simple_oauth\Server\AuthorizationServerFactoryInterface;
use GuzzleHttp\Psr7\Response;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use League\OAuth2\Server\RequestTypes\AuthorizationRequest;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class OAuth2 Authorize Controller.
 */
class Oauth2AuthorizeController extends ControllerBase {

  /**
   * The message factory.
   *
   * @var \Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface
   */
  protected HttpMessageFactoryInterface $httpMessageFactory;

  /**
   * The authorization server factory.
   *
   * @var \Drupal\simple_oauth\Server\AuthorizationServerFactoryInterface
   */
  protected AuthorizationServerFactoryInterface $authorizationServerFactory;

  /**
   * The known client repository service.
   *
   * @var \Drupal\simple_oauth\KnownClientsRepositoryInterface
   */
  protected KnownClientsRepositoryInterface $knownClientRepository;

  /**
   * The client repository.
   *
   * @var \League\OAuth2\Server\Repositories\ClientRepositoryInterface
   */
  protected ClientRepositoryInterface $clientRepository;

  /**
   * The simple_oauth logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * The OAuth2 scope provider.
   *
   * @var \Drupal\simple_oauth\Oauth2ScopeProviderInterface
   */
  protected Oauth2ScopeProviderInterface $scopeProvider;

  /**
   * Oauth2AuthorizeController construct.
   *
   * @param \Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface $http_message_factory
   *   The PSR-7 converter.
   * @param \Drupal\simple_oauth\Server\AuthorizationServerFactoryInterface $authorization_server_factory
   *   The authorization server factory.
   * @param \Drupal\simple_oauth\KnownClientsRepositoryInterface $known_clients_repository
   *   The known client repository service.
   * @param \League\OAuth2\Server\Repositories\ClientRepositoryInterface $client_repository
   *   The client repository service.
   * @param \Psr\Log\LoggerInterface $logger
   *   The simple_oauth logger channel.
   * @param \Drupal\simple_oauth\Oauth2ScopeProviderInterface $scope_provider
   *   The OAuth2 scope provider.
   */
  public function __construct(
    HttpMessageFactoryInterface $http_message_factory,
    AuthorizationServerFactoryInterface $authorization_server_factory,
    KnownClientsRepositoryInterface $known_clients_repository,
    ClientRepositoryInterface $client_repository,
    LoggerInterface $logger,
    Oauth2ScopeProviderInterface $scope_provider,
  ) {
    $this->httpMessageFactory = $http_message_factory;
    $this->authorizationServerFactory = $authorization_server_factory;
    $this->knownClientRepository = $known_clients_repository;
    $this->clientRepository = $client_repository;
    $this->logger = $logger;
    $this->scopeProvider = $scope_provider;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('psr7.http_message_factory'),
      $container->get('simple_oauth.server.authorization_server.factory'),
      $container->get('simple_oauth.known_clients'),
      $container->get('simple_oauth.repositories.client'),
      $container->get('logger.channel.simple_oauth'),
      $container->get('simple_oauth.oauth2_scope.provider')
    );
  }

  /**
   * Authorizes the code generation or prints the confirmation form.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The incoming request.
   *
   * @return mixed
   *   The response.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function authorize(Request $request) {
    $client_id = $request->get('client_id');
    $server_response = new Response();

    try {
      $server_request = $this->httpMessageFactory->createRequest($request);
      if (empty($client_id)) {
        throw OAuthServerException::invalidRequest('client_id');
      }
      $client_entity = $this->clientRepository->getClientEntity($client_id);
      if (empty($client_entity)) {
        throw OAuthServerException::invalidClient($server_request);
      }
      $client_drupal_entity = $client_entity->getDrupalEntity();
      $automatic_authorization = (bool) $client_drupal_entity->get('automatic_authorization')->value;

      $server = $this->authorizationServerFactory->get($client_drupal_entity);
      $auth_request = $server->validateAuthorizationRequest($server_request);

      // Validate that at least one scope is provided either in the request
      // or as default scopes on the consumer.
      $requested_scopes = $request->get('scope');
      if (empty($requested_scopes) && $client_drupal_entity->get('authorization_code_scopes')->isEmpty()) {
        throw OAuthServerException::invalidRequest('scope');
      }

      // Validate scopes for the authorization_code grant type.
      // This validates both requested scopes and default scopes.
      $this->validateScopes($auth_request, $client_drupal_entity, $requested_scopes);

      // Extract scope names from the authorization request.
      $scope_names = array_map(function ($scope) {
        return $scope->getIdentifier();
      }, $auth_request->getScopes());

      if ($this->currentUser()->isAnonymous()) {
        return $this->redirectAnonymous($request);
      }

      // Once the user has logged in set the user on the AuthorizationRequest.
      $user_entity = new UserEntity();
      $user_entity->setIdentifier($this->currentUser->id());
      $auth_request->setUser($user_entity);

      // User may skip the grant step if the client has automatic authorization
      // enabled or is known.
      if ($automatic_authorization || $this->knownClientRepository->isAuthorized($this->currentUser()->id(), $client_drupal_entity, $scope_names)) {
        $can_grant = $this->currentUser()->hasPermission('grant simple_oauth codes');
        $auth_request->setAuthorizationApproved($can_grant);
        $response = $server->completeAuthorizationRequest($auth_request, $server_response);
      }
      else {
        $response = $this->formBuilder()->getForm(Oauth2AuthorizeForm::class, $server, $auth_request);
      }
    }
    catch (OAuthServerException $exception) {
      $this->logger->error($exception->getMessage() . ' Hint: ' . $exception->getHint() . '.');
      $response = $exception->generateHttpResponse($server_response);
    }

    return $response;
  }

  /**
   * Redirect anonymous user to user login.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The incoming request.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Returns redirect response.
   */
  protected function redirectAnonymous(Request $request): RedirectResponse {
    $message = $this->t('An external client application is requesting access to your data in this site. Please log in first to authorize the operation.');
    $this->messenger()->addStatus($message);
    // If the user is not logged in.
    $destination = Url::fromRoute('oauth2_token.authorize', [], [
      'query' => UrlHelper::parse('/?' . $request->getQueryString())['query'],
    ]);
    $url = Url::fromRoute('user.login', [], [
      'query' => ['destination' => $destination->toString()],
    ]);
    // Client ID and secret may be passed as Basic Auth.
    return new RedirectResponse($url->toString());
  }

  /**
   * Validates scopes for the authorization code grant type.
   *
   * This method validates both requested scopes and default scopes to ensure
   * they are enabled for the authorization_code grant type.
   *
   * @param \League\OAuth2\Server\RequestTypes\AuthorizationRequest $auth_request
   *   The authorization request containing scopes to validate.
   * @param \Drupal\consumers\Entity\ConsumerInterface $client_drupal_entity
   *   The consumer entity.
   * @param string|null $requested_scopes
   *   The requested scopes from the request parameter.
   *
   * @throws \League\OAuth2\Server\Exception\OAuthServerException
   *   Thrown if any scope is not enabled for the authorization_code grant type.
   */
  protected function validateScopes(AuthorizationRequest $auth_request, ConsumerInterface $client_drupal_entity, ?string $requested_scopes): void {
    // If scopes were requested, validate them from the auth request.
    if (!empty($requested_scopes)) {
      foreach ($auth_request->getScopes() as $scope) {
        if ($scope instanceof ScopeEntity && !$scope->getScopeObject()->isGrantTypeEnabled('authorization_code')) {
          throw OAuthServerException::invalidScope($scope->getIdentifier());
        }
      }
    }
    // If no scopes were requested, validate the default scopes.
    elseif (!$client_drupal_entity->get('authorization_code_scopes')->isEmpty()) {
      $this->validateDefaultScopes($client_drupal_entity);
    }
  }

  /**
   * Validates default scopes for the authorization code grant type.
   *
   * @param \Drupal\consumers\Entity\ConsumerInterface $client_drupal_entity
   *   The consumer entity containing default scopes.
   *
   * @throws \League\OAuth2\Server\Exception\OAuthServerException
   *   Thrown if any default scope is not enabled for the authorization_code
   *   grant type.
   */
  protected function validateDefaultScopes(ConsumerInterface $client_drupal_entity): void {
    // Get default scopes from the consumer entity.
    $default_scope_ids = array_column(
      $client_drupal_entity->get('authorization_code_scopes')->getValue(),
      'scope_id'
    );

    // Load and validate each default scope.
    $scope_objects = $this->scopeProvider->loadMultiple($default_scope_ids);
    foreach ($scope_objects as $scope_id => $scope_object) {
      if ($scope_object && !$scope_object->isGrantTypeEnabled('authorization_code')) {
        throw OAuthServerException::invalidScope($scope_id);
      }
    }
  }

}
