<?php

namespace Drupal\simple_oauth\Controller;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\Crypt;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Lock\LockBackendInterface;
use Drupal\simple_oauth\Server\AuthorizationServerFactoryInterface;
use GuzzleHttp\Psr7\Response;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LogLevel;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class OAuth2 Token Controller.
 */
class Oauth2Token extends ControllerBase {

  /**
   * The authorization server factory.
   *
   * @var \Drupal\simple_oauth\Server\AuthorizationServerFactoryInterface
   */
  protected AuthorizationServerFactoryInterface $authorizationServerFactory;

  /**
   * The message factory.
   *
   * @var \Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface
   */
  protected HttpMessageFactoryInterface $httpMessageFactory;

  /**
   * The client repository.
   *
   * @var \League\OAuth2\Server\Repositories\ClientRepositoryInterface
   */
  protected ClientRepositoryInterface $clientRepository;

  /**
   * The lock backend.
   *
   * @var \Drupal\Core\Lock\LockBackendInterface
   */
  protected LockBackendInterface $lock;

  /**
   * The simple_oauth logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  /**
   * Oauth2Token constructor.
   *
   * @param \Drupal\simple_oauth\Server\AuthorizationServerFactoryInterface $authorization_server_factory
   *   The authorization server factory.
   * @param \Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface $http_message_factory
   *   The PSR-7 converter.
   * @param \League\OAuth2\Server\Repositories\ClientRepositoryInterface $client_repository
   *   The client repository service.
   * @param \Drupal\Core\Lock\LockBackendInterface $lock
   *   The lock backend.
   * @param \Psr\Log\LoggerInterface $logger
   *   The simple_oauth logger channel.
   */
  public function __construct(
    AuthorizationServerFactoryInterface $authorization_server_factory,
    HttpMessageFactoryInterface $http_message_factory,
    ClientRepositoryInterface $client_repository,
    LockBackendInterface $lock,
    LoggerInterface $logger,
  ) {
    $this->authorizationServerFactory = $authorization_server_factory;
    $this->httpMessageFactory = $http_message_factory;
    $this->clientRepository = $client_repository;
    $this->lock = $lock;
    $this->logger = $logger;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('simple_oauth.server.authorization_server.factory'),
      $container->get('psr7.http_message_factory'),
      $container->get('simple_oauth.repositories.client'),
      $container->get('lock'),
      $container->get('logger.channel.simple_oauth')
    );
  }

  /**
   * Processes POST requests to /oauth/token.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   The response.
   *
   * @throws \Exception
   */
  public function token(Request $request): ResponseInterface {
    $server_request = $this->httpMessageFactory->createRequest($request);
    $server_response = new Response();
    $client_id = $request->get('client_id');
    $grant_type = $request->get('grant_type');
    $scopes = $request->get('scope');

    $lock_key = $this->createLockKey($request);

    try {
      // Try to acquire the lock.
      while (!$this->lock->acquire($lock_key)) {
        // If we can't acquire the lock, wait for it.
        if ($this->lock->wait($lock_key)) {
          // Timeout reached after 30 seconds.
          throw OAuthServerException::accessDenied('Request timed out. Could not acquire lock.');
        }
      }

      if (empty($client_id)) {
        throw OAuthServerException::invalidRequest('client_id');
      }
      $client_entity = $this->clientRepository->getClientEntity($client_id);
      if (empty($client_entity)) {
        throw OAuthServerException::invalidClient($server_request);
      }
      $client_drupal_entity = $client_entity->getDrupalEntity();

      // Omitting scopes is not allowed when dealing with client_credentials
      // and no default scopes are set.
      if (
        $grant_type === 'client_credentials' &&
        empty($scopes) &&
        $client_drupal_entity->get('scopes')->isEmpty()
      ) {
        throw OAuthServerException::invalidRequest('scope');
      }

      // Respond to the incoming request and fill in the response.
      $server = $this->authorizationServerFactory->get($client_drupal_entity);
      $response = $server->respondToAccessTokenRequest($server_request, $server_response);
    }
    catch (OAuthServerException $exception) {
      $this->logger->log(
        $exception->getCode() < 500 ? LogLevel::NOTICE : LogLevel::ERROR,
        $exception->getMessage() . ' Hint: ' . $exception->getHint() . '.'
      );
      $response = $exception->generateHttpResponse($server_response);
    }
    finally {
      // Release the lock.
      $this->lock->release($lock_key);
    }

    return $response;
  }

  /**
   * Creates a lock key for the request.
   *
   * The key consists of the request content, in this case the
   * grant payload.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request.
   *
   * @return string
   *   The lock key.
   */
  protected function createLockKey(Request $request): string {
    return Crypt::hashBase64(Json::encode($request->request->all()));
  }

}
