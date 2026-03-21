<?php

namespace Drupal\Tests\simple_oauth\Functional;

use Drupal\Component\Serialization\Json;
use League\OAuth2\Server\CryptTrait;

/**
 * The refresh tests.
 *
 * @group simple_oauth
 */
class RefreshFunctionalTest extends TokenBearerFunctionalTestBase {

  use CryptTrait;

  /**
   * The refresh token.
   *
   * @var string
   */
  protected string $refreshToken;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->scope = 'authenticated';
    $valid_payload = [
      'grant_type' => 'password',
      'client_id' => $this->client->getClientId(),
      'client_secret' => $this->clientSecret,
      'username' => $this->user->getAccountName(),
      'password' => $this->user->pass_raw,
      'scope' => $this->scope,
    ];
    $response = $this->post($this->url, $valid_payload);
    $this->assertValidTokenResponse($response, TRUE);
    $body = (string) $response->getBody();
    $parsed_response = Json::decode($body);
    $this->refreshToken = $parsed_response['refresh_token'];
  }

  /**
   * Test the valid Refresh grant.
   */
  public function testRefreshGrant(): void {
    // 1. Test the valid response.
    $valid_payload = [
      'grant_type' => 'refresh_token',
      'client_id' => $this->client->getClientId(),
      'client_secret' => $this->clientSecret,
      'refresh_token' => $this->refreshToken,
      'scope' => $this->scope,
    ];
    $response = $this->post($this->url, $valid_payload);
    $this->assertValidTokenResponse($response, TRUE);

    // 2. Test the valid without scopes.
    // We need to use the new refresh token, the old one is revoked.
    $parsed_response = Json::decode((string) $response->getBody());
    $valid_payload = [
      'grant_type' => 'refresh_token',
      'client_id' => $this->client->getClientId(),
      'client_secret' => $this->clientSecret,
      'refresh_token' => $parsed_response['refresh_token'],
      'scope' => $this->scope,
    ];
    $response = $this->post($this->url, $valid_payload);
    $this->assertValidTokenResponse($response, TRUE);

    // 3. Test that the token token was revoked.
    $valid_payload = [
      'grant_type' => 'refresh_token',
      'client_id' => $this->client->getClientId(),
      'client_secret' => $this->clientSecret,
      'refresh_token' => $this->refreshToken,
    ];
    $response = $this->post($this->url, $valid_payload);
    $parsed_response = Json::decode((string) $response->getBody());
    $this->assertSame(401, $response->getStatusCode());
    $this->assertSame('invalid_request', $parsed_response['error']);
  }

  /**
   * Data provider for ::testMissingRefreshGrant.
   */
  public function missingRefreshGrantProvider(): array {
    return [
      'grant_type' => [
        'grant_type',
        'invalid_grant',
        400,
      ],
      'client_id' => [
        'client_id',
        'invalid_request',
        400,
      ],
      'client_secret' => [
        'client_secret',
        'invalid_client',
        401,
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
    $valid_payload = [
      'grant_type' => 'refresh_token',
      'client_id' => $this->client->getClientId(),
      'client_secret' => $this->clientSecret,
      'refresh_token' => $this->refreshToken,
      'scope' => $this->scope,
    ];

    $invalid_payload = $valid_payload;
    unset($invalid_payload[$key]);
    $response = $this->post($this->url, $invalid_payload);
    $parsed_response = Json::decode((string) $response->getBody());
    $this->assertSame($error, $parsed_response['error'], sprintf('Correct error code %s', $error));
    $this->assertSame($code, $response->getStatusCode(), sprintf('Correct status code %d', $code));
  }

  /**
   * Data provider for ::invalidRefreshProvider.
   */
  public function invalidRefreshProvider(): array {
    return [
      'grant_type' => [
        'grant_type',
        'invalid_grant',
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
        'invalid_request',
        401,
      ],
    ];
  }

  /**
   * Test invalid Refresh grant.
   *
   * @dataProvider invalidRefreshProvider
   */
  public function testInvalidRefreshGrant(string $key, string $error, int $code): void {
    $valid_payload = [
      'grant_type' => 'refresh_token',
      'client_id' => $this->client->getClientId(),
      'client_secret' => $this->clientSecret,
      'refresh_token' => $this->refreshToken,
      'scope' => $this->scope,
    ];

    $invalid_payload = $valid_payload;
    $invalid_payload[$key] = $this->randomString();
    $response = $this->post($this->url, $invalid_payload);
    $parsed_response = Json::decode((string) $response->getBody());
    $this->assertSame($error, $parsed_response['error'], sprintf('Correct error code %s', $error));
    $this->assertSame($code, $response->getStatusCode(), sprintf('Correct status code %d', $code));
  }

}
