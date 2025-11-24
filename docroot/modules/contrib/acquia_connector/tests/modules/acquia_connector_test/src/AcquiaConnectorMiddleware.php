<?php

namespace Drupal\acquia_connector_test;

use Drupal\Component\Serialization\Json;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\State\StateInterface;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;

/**
 * Guzzle middleware for the Acquia Connector API.
 */
class AcquiaConnectorMiddleware {

  /**
   * The state.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  private StateInterface $state;

  /**
   * Constructs a new AcquiaConnectorMiddleware object.
   *
   * @param \Drupal\Core\State\StateInterface $state
   *   The state.
   */
  public function __construct(StateInterface $state) {
    $this->state = $state;
  }

  /**
   * Invoked method that returns a promise.
   */
  public function __invoke() {
    return function ($handler) {
      return function (RequestInterface $request, array $options) use ($handler) {
        $uri = $request->getUri();
        if ($uri->getHost() === 'accounts.acquia.com') {
          // Test valid/invalid ID
          if ($uri->getPath() === '/api/auth/oauth/authorize') {
            $query = UrlHelper::parse($request->getRequestTarget());
            return ($query['query']['client_id'] === '38357830-bacd-4b4d-a356-f508c6ddecf8') ? new FulfilledPromise(new Response(200)) : new FulfilledPromise(new Response(403));
          }
          $oauth_content = [];
          parse_str((string) $request->getBody(), $oauth_content);
          // Following if condition will be removed once we eliminate all
          // account authentication stuff and relying solely on the Acquia ID.
          if (!isset($oauth_content['grant_type']) || (isset($oauth_content['grant_type']) && $oauth_content['grant_type'] != 'client_credentials')) {
            $oauth_content = Json::decode((string) $request->getBody());
          }

          // API Key / Secret Response.
          if ($oauth_content['grant_type'] === 'client_credentials' && $oauth_content['client_id'] === 'VALID_KEY' && $oauth_content['client_secret'] === 'VALID_SECRET') {
            return new FulfilledPromise(
              new Response(
                200,
                [],
                Json::encode([
                  'access_token' => 'ACCESS_TOKEN',
                  'token_type' => 'bearer',
                  'expires_in' => 300,
                ])
              )
            );
          }
          // Client Credential Error response.
          if ($oauth_content['grant_type'] === 'client_credentials' && $oauth_content['client_id'] === 'BAD_KEY' && $oauth_content['client_secret'] === 'BAD_SECRET') {
            return new FulfilledPromise(
              new Response(
                400,
                [],
                json_encode([
                  'error' => 'invalid_client',
                  'error_description' => 'The client credentials are invalid',
                ])
              )
            );
          }
          // Client Credential missing keys error response.
          if ($oauth_content['grant_type'] === 'client_credentials') {
            return new FulfilledPromise(
              new Response(
                400,
                [],
                json_encode([
                  'error' => 'invalid_client',
                  'error_description' => 'client credentials are required',
                ])
              )
            );
          }
          // Oauth successful return response.
          if ($oauth_content['grant_type'] === 'authorization_code' && $oauth_content['code'] === 'AUTHORIZATION_SUCCESSFUL') {
            return new FulfilledPromise(
              new Response(
                200,
                [],
                Json::encode([
                  'access_token' => 'ACCESS_TOKEN',
                ])
              )
            );
          }
          // Authorization Error response.
          if ($oauth_content['grant_type'] === 'authorization_code' && $oauth_content['code'] === 'AUTHORIZATION_ERROR') {
            return new FulfilledPromise(
              new Response(
                400,
                [],
                json_encode([
                  'error' => 'invalid_grant',
                  'error_description' => 'Authorization code doesn\'t exist or is invalid for the client',
                ])
              )
            );
          }
        }

        if ($uri->getHost() === 'cloud.acquia.com') {
          $authorization = $request->getHeaderLine('Authorization');
          if ($authorization === '') {
            return new FulfilledPromise(
              new Response(
                403,
                [],
                ''
              )
            );
          }
          if ($uri->getPath() === '/api/account/tokens') {
            return new FulfilledPromise(
              new Response(
                201,
                [],
                Json::encode([
                  "api_key" => "VALID_KEY",
                  "api_secret" => "VALID_SECRET",
                  "client_id" => "137bd484-dcc8-4950-a784-1f01de7f6378",
                  "client_secret" => "4DmbUmGiUkafdjcZk2yV6u17jPmmunwt8/47mKdAQIc=",
                  "_links" => [
                    "self" => [
                      "href" => "https://cloud.acquia.com/api/account/tokens",
                    ],
                    "parent" => [
                      "href" => "https://cloud.acquia.com/api/account",
                    ],
                    "notification" => [
                      "href" => "https://cloud.acquia.com/api/notifications/ab142771-826e-42b0-a53c-e112b70448d2",
                    ],
                  ],
                ])
              )
            );
          }
          if ($uri->getPath() === '/api/applications') {
            if ($authorization === 'Bearer ACCESS_TOKEN_NO_APPLICATIONS') {
              return new FulfilledPromise(
                new Response(
                  200,
                  [],
                  Json::encode([
                    'total' => 0,
                    '_embedded' => [
                      'items' => [],
                    ],
                  ])
                )
              );
            }
            if ($authorization === 'Bearer ACCESS_TOKEN_ONE_APPLICATION') {
              return new FulfilledPromise(
                new Response(
                  200,
                  [],
                  Json::encode([
                    'total' => 0,
                    '_embedded' => [
                      'items' => [
                        [
                          'id' => 1234,
                          'uuid' => 'a47ac10b-58cc-4372-a567-0e02b2c3d470',
                          'name' => 'Sample application 1',
                          'subscription' => [
                            'uuid' => 'f47ac10b-58cc-4372-a567-0e02b2c3d470',
                            'name' => 'Sample subscription',
                          ],
                        ],
                      ],
                    ],
                  ])
                )
              );
            }
            if ($authorization === 'Bearer ACCESS_TOKEN_ERROR_GETTING_APPLICATION_KEYS') {
              return new FulfilledPromise(
                new Response(
                  200,
                  [],
                  Json::encode([
                    'total' => 0,
                    '_embedded' => [
                      'items' => [
                        [
                          'id' => 1234,
                          'uuid' => '647061f7-9971-4b24-9ebb-59eea154d507',
                          'name' => 'Sample application 1',
                          'subscription' => [
                            'uuid' => 'f47ac10b-58cc-4372-a567-0e02b2c3d470',
                            'name' => 'Sample subscription',
                          ],
                        ],
                      ],
                    ],
                  ])
                )
              );
            }
            if ($authorization === 'Bearer ACCESS_TOKEN_MULTIPLE_APPLICATIONS') {
              return new FulfilledPromise(
                new Response(
                  200,
                  [],
                  Json::encode([
                    'total' => 0,
                    '_embedded' => [
                      'items' => [
                        [
                          'id' => 1234,
                          'uuid' => 'a47ac10b-58cc-4372-a567-0e02b2c3d470',
                          'name' => 'Sample application 1',
                          'subscription' => [
                            'uuid' => 'f47ac10b-58cc-4372-a567-0e02b2c3d470',
                            'name' => 'Sample subscription',
                          ],
                        ],
                        [
                          'id' => 5678,
                          'uuid' => 'a47ac10b-58cc-4372-a567-0e02b2c3d471',
                          'name' => 'Sample application 2',
                          'subscription' => [
                            'uuid' => 'f47ac10b-58cc-4372-a567-0e02b2c3d470',
                            'name' => 'Sample subscription',
                          ],
                        ],
                      ],
                    ],
                  ])
                )
              );
            }
          }
          if ($uri->getPath() === '/api/applications/647061f7-9971-4b24-9ebb-59eea154d507/settings/keys') {
            return new FulfilledPromise(
              new Response(
                500,
                [],
                ''
              )
            );
          }
          if ($uri->getPath() === '/api/applications/a47ac10b-58cc-4372-a567-0e02b2c3d470/settings/keys') {
            return new FulfilledPromise(
              new Response(
                200,
                [],
                Json::encode([
                  'acquia_connector' => [
                    'identifier' => 'ABCD-12345',
                    'key' => '12345678f5325ea35d63a6c3debcd225',
                  ],
                ])
              )
            );
          }
          if ($uri->getPath() === '/api/applications/a47ac10b-58cc-4372-a567-0e02b2c3d470') {
            return new FulfilledPromise(
              new Response(
                200,
                [],
                Json::encode([
                  'id' => 1234,
                  'uuid' => 'a47ac10b-58cc-4372-a567-0e02b2c3d470',
                  'name' => 'Sample application 1',
                  'subscription' => [
                    'uuid' => 'f47ac10b-58cc-4372-a567-0e02b2c3d470',
                    'name' => 'Sample subscription',
                  ],
                ])
              )
            );
          }
          if ($uri->getPath() === '/api/subscriptions/f47ac10b-58cc-4372-a567-0e02b2c3d470') {
            return new FulfilledPromise(
              new Response(
                200,
                [],
                Json::encode([
                  'id' => 329876,
                  'uuid' => 'f47ac10b-58cc-4372-a567-0e02b2c3d470',
                  'name' => 'Sample subscription',
                  'expire_at' => '2030-05-12T00:00:00',
                  'flags' => [
                    'active' => TRUE,
                    'expired' => FALSE,
                  ],
                ])
              )
            );
          }
          if ($uri->getPath() === '/test-retry-middleware') {
            if ($authorization === 'Bearer ACCESS_TOKEN_RETRY_MIDDLEWARE') {
              return new FulfilledPromise(
                new Response(
                  401,
                  [],
                  ''
                )
              );
            }
            if ($authorization === 'Bearer ACCESS_TOKEN_REFRESHED') {
              return new FulfilledPromise(
                new Response(
                  200,
                  [],
                  ''
                )
              );
            }
          }
        }

        // Otherwise, no intervention. We defer to the handler stack.
        return $handler($request, $options);
      };
    };
  }

}
