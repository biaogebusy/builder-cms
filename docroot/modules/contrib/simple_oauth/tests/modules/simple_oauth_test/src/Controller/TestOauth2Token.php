<?php

namespace Drupal\simple_oauth_test\Controller;

use Drupal\Core\Lock\LockBackendInterface;
use Drupal\Core\State\StateInterface;
use Drupal\simple_oauth\Controller\Oauth2Token;
use Drupal\simple_oauth\Server\AuthorizationServerFactoryInterface;
use GuzzleHttp\Psr7\Response;
use League\OAuth2\Server\Repositories\ClientRepositoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/**
 * Provides a test controller to override the token creation behavior.
 */
class TestOauth2Token extends Oauth2Token {

  /**
   * Constructs a test Oauth2 token controller.
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
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   */
  public function __construct(
    AuthorizationServerFactoryInterface $authorization_server_factory,
    HttpMessageFactoryInterface $http_message_factory,
    ClientRepositoryInterface $client_repository,
    LockBackendInterface $lock,
    LoggerInterface $logger,
    protected StateInterface $state,
  ) {
    parent::__construct(
      $authorization_server_factory,
      $http_message_factory,
      $client_repository,
      $lock,
      $logger,
    );
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
      $container->get('logger.channel.simple_oauth'),
      $container->get('state'),
    );
  }

  /**
   * {@inheritdoc}
   *
   * @see \Drupal\simple_oauth_test\Routing\SimpleOauthTestRouteSubscriber::alterRoutes()
   */
  public function token(Request $request): ResponseInterface {
    $override_mode = $this->state->get('simple_oauth_test_token_controller_override');
    switch ($override_mode) {
      case 'exception':
        throw new ServiceUnavailableHttpException();

      case 'invalid_json':
        return new Response('this is: not JSON');

      case 'missing_access_token':
        return new Response(json_encode([
          'this_is_not' => 'an_access_token',
        ]));

      case 'invalid_jwt':
        return new Response(json_encode([
          'access_token' => 'this_does_not_contain_a_period',
        ]));

      case 'invalid_base64':
        return new Response(json_encode([
          'access_token' => '.?<-this_is_not_base64_encoded',
        ]));

      case 'invalid_jwt_json':
        return new Response(json_encode([
          'access_token' => '.this/is/base64/safe/but/not/valid/json/when/decoded',
        ]));

      case 'missing_claim':
        return new Response(json_encode([
          'access_token' => '.' . base64_encode(json_encode(
            'this is not an array',
          )),
        ]));

      case 'missing_jti':
        return new Response(json_encode([
          'access_token' => '.' . base64_encode(json_encode([
            'this_is_not' => 'a_jti',
          ])),
        ]));
    }

    return parent::token($request);
  }

}
