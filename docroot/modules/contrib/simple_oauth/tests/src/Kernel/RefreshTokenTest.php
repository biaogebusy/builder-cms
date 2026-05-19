<?php

namespace Drupal\Tests\simple_oauth\Kernel;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;
use GuzzleHttp\Psr7\Query;
use Symfony\Component\HttpFoundation\Request;

/**
 * The refresh token tests.
 *
 * @group simple_oauth
 */
class RefreshTokenTest extends AuthorizedRequestBase {

  /**
   * The refresh token.
   *
   * @var string
   */
  protected $refreshToken;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->grantPermissions(Role::load(RoleInterface::AUTHENTICATED_ID), [
      'grant simple_oauth codes',
    ]);
    $this->client->set('automatic_authorization', TRUE);
    $this->client->set('user_id', $this->user)->save();
    $this->client->save();
    $current_user = $this->container->get('current_user');
    $current_user->setAccount($this->user);

    $authorize_url = Url::fromRoute('oauth2_token.authorize')->toString();

    $parameters = [
      'response_type' => 'code',
      'client_id' => $this->client->getClientId(),
      'client_secret' => $this->clientSecret,
      'scope' => $this->scope,
      'redirect_uri' => $this->redirectUri,
    ];
    $request = Request::create($authorize_url, 'GET', $parameters);
    $response = $this->httpKernel->handle($request);
    $parsed_url = parse_url($response->headers->get('location'));
    $parsed_query = Query::parse($parsed_url['query']);
    $code = $parsed_query['code'];
    $parameters = [
      'grant_type' => 'authorization_code',
      'client_id' => $this->client->getClientId(),
      'client_secret' => $this->clientSecret,
      'code' => $code,
      'scope' => $this->scope,
      'redirect_uri' => $this->redirectUri,
    ];
    $request = Request::create($this->url->toString(), 'POST', $parameters);
    $response = $this->httpKernel->handle($request);
    $parsed_response = Json::decode((string) $response->getContent());
    $this->refreshToken = $parsed_response['refresh_token'];
  }

  /**
   * Test the valid Refresh grant.
   */
  public function testRefreshGrant(): void {
    // 1. Test the valid response.
    $parameters = [
      'grant_type' => 'refresh_token',
      'client_id' => $this->client->getClientId(),
      'client_secret' => $this->clientSecret,
      'refresh_token' => $this->refreshToken,
      'scope' => $this->scope,
    ];
    $request = Request::create($this->url->toString(), 'POST', $parameters);
    $response = $this->httpKernel->handle($request);
    $parsed_response = $this->assertValidTokenResponse($response, TRUE);

    // 2. Test a valid response with the new refresh token, the old one is
    // revoked.
    $parameters = [
      'grant_type' => 'refresh_token',
      'client_id' => $this->client->getClientId(),
      'client_secret' => $this->clientSecret,
      'refresh_token' => $parsed_response['refresh_token'],
      'scope' => $this->scope,
    ];
    $request = Request::create($this->url->toString(), 'POST', $parameters);
    $response = $this->httpKernel->handle($request);
    $this->assertValidTokenResponse($response, TRUE);

    // 3. Test that the token was revoked.
    $parameters = [
      'grant_type' => 'refresh_token',
      'client_id' => $this->client->getClientId(),
      'client_secret' => $this->clientSecret,
      'refresh_token' => $this->refreshToken,
    ];
    $request = Request::create($this->url->toString(), 'POST', $parameters);
    $response = $this->httpKernel->handle($request);
    $this->assertEquals(400, $response->getStatusCode());
    $parsed_response = Json::decode((string) $response->getContent());
    $this->assertSame('invalid_grant', $parsed_response['error']);
  }

  /**
   * Data provider for ::testMissingRefreshGrant.
   */
  public static function missingRefreshGrantProvider(): array {
    return [
      'grant_type' => [
        'grant_type',
        'unsupported_grant_type',
        400,
      ],
      'client_id' => [
        'client_id',
        'invalid_request',
        400,
      ],
      'client_secret' => [
        'client_secret',
        'invalid_request',
        400,
      ],
      'refresh_token' => [
        'refresh_token',
        'invalid_request',
        400,
      ],
    ];
  }

  /**
   * Test invalid Refresh grant.
   *
   * @dataProvider missingRefreshGrantProvider
   */
  public function testMissingRefreshGrant(string $key, string $error, int $code): void {
    $parameters = [
      'grant_type' => 'refresh_token',
      'client_id' => $this->client->getClientId(),
      'client_secret' => $this->clientSecret,
      'refresh_token' => $this->refreshToken,
      'scope' => $this->scope,
    ];

    unset($parameters[$key]);
    $request = Request::create($this->url->toString(), 'POST', $parameters);
    $response = $this->httpKernel->handle($request);
    $parsed_response = Json::decode((string) $response->getContent());
    $this->assertEquals($error, $parsed_response['error'], sprintf('Correct error code %s', $error));
    $this->assertEquals($code, $response->getStatusCode(), sprintf('Correct status code %d', $code));
  }

  /**
   * Data provider for ::invalidRefreshProvider.
   */
  public static function invalidRefreshProvider(): array {
    return [
      'grant_type' => [
        'grant_type',
        'unsupported_grant_type',
        400,
      ],
      'client_id' => [
        'client_id',
        'invalid_client',
        401,
      ],
      'client_secret' => [
        'client_secret',
        'invalid_client',
        401,
      ],
      'refresh_token' => [
        'refresh_token',
        'invalid_grant',
        400,
      ],
    ];
  }

  /**
   * Test invalid Refresh grant.
   *
   * @dataProvider invalidRefreshProvider
   */
  public function testInvalidRefreshGrant(string $key, string $error, int $code): void {
    $parameters = [
      'grant_type' => 'refresh_token',
      'client_id' => $this->client->getClientId(),
      'client_secret' => $this->clientSecret,
      'refresh_token' => $this->refreshToken,
      'scope' => $this->scope,
    ];

    $parameters[$key] = $this->randomString();
    $request = Request::create($this->url->toString(), 'POST', $parameters);
    $response = $this->httpKernel->handle($request);
    $parsed_response = Json::decode((string) $response->getContent());
    $this->assertEquals($error, $parsed_response['error'], sprintf('Correct error code %s', $error));
    $this->assertEquals($code, $response->getStatusCode(), sprintf('Correct status code %d', $code));
  }

}
