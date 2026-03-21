<?php

namespace Drupal\simple_oauth\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\simple_oauth\Plugin\Oauth2GrantManagerInterface;
use GuzzleHttp\Psr7\Response;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class Oauth2Token extends ControllerBase {

  /**
   * @var \Drupal\simple_oauth\Plugin\Oauth2GrantManagerInterface
   */
  protected $grantManager;

  /**
   * @var \League\OAuth2\Server\Repositories\ClientRepositoryInterface
   */
  protected $clientRepository;

  /**
   * Oauth2Token constructor.
   *
   * @param \Drupal\simple_oauth\Plugin\Oauth2GrantManagerInterface $grant_manager
   *   The grant manager.
   * @param \League\OAuth2\Server\Repositories\ClientRepositoryInterface $client_repository
   *   The client repository service.
   */
  public function __construct(Oauth2GrantManagerInterface $grant_manager, ClientRepositoryInterface $client_repository) {
    $this->grantManager = $grant_manager;
    $this->clientRepository = $client_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.oauth2_grant.processor'),
      $container->get('simple_oauth.repositories.client')
    );
  }

  /**
   * Processes POST requests to /oauth/token.
   */
  public function token(ServerRequestInterface $request) {
    // Extract the grant type from the request body.
    $body = $request->getParsedBody();
    $grant_type_id = !empty($body['grant_type']) ? $body['grant_type'] : 'implicit';
    $consumer_entity = NULL;
    if (!empty($body['client_id'])) {
      $client_drupal_entity = $this->clientRepository
        ->getClientEntity($body['client_id']);
      if (empty($client_drupal_entity)) {
        return OAuthServerException::invalidClient($request)->generateHttpResponse(new Response());
      }
      $consumer_entity = $client_drupal_entity->getDrupalEntity();
    }
    // Get the auth server object from that uses the League library.
    try {
      // Respond to the incoming request and fill in the response.
      $auth_server = $this->grantManager->getAuthorizationServer($grant_type_id, $consumer_entity);
      $response = $this->handleToken($request, $auth_server);
    }
    catch (OAuthServerException $exception) {
      watchdog_exception('simple_oauth', $exception);
      $response = $exception->generateHttpResponse(new Response());
    }
    return $response;
  }

  /**
   * Handles the token processing.
   *
   * @param \Psr\Http\Message\ServerRequestInterface $psr7_request
   *   The psr request.
   * @param \League\OAuth2\Server\AuthorizationServer $auth_server
   *   The authorization server.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   The response.
   *
   * @throws \League\OAuth2\Server\Exception\OAuthServerException
   */
  protected function handleToken(ServerRequestInterface $psr7_request, AuthorizationServer $auth_server) {
    // Instantiate a new PSR-7 response object so the library can fill it.
    return $auth_server->respondToAccessTokenRequest($psr7_request, new Response());
  }

}
