<?php

namespace Drupal\Tests\simple_oauth\Functional;

use Drupal\Component\Serialization\Json;

/**
 * The password grant type tests.
 *
 * @group simple_oauth
 */
class PasswordFunctionalTest extends TokenBearerFunctionalTestBase {

  /**
   * Ensure public clients still validate client secrets when they are sent.
   */
  public function testValidateSecretEvenIfPublic(): void {
    $this->client->set('confidential', FALSE)->save();
    // 1. Send an invalid secret.
    $invalid_payload = [
      'grant_type' => 'password',
      'client_id' => $this->client->getClientId(),
      'client_secret' => 'invalidClientSecret',
      'username' => $this->user->getAccountName(),
      'password' => $this->user->pass_raw,
      'scope' => $this->scope,
    ];
    $response = $this->post($this->url, $invalid_payload);
    $this->assertEquals(401, $response->getStatusCode());
    // 2. A valid secret is validated correctly.
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
    // 3. The client has a secret registered, so it's always required.
    $invalid_payload = [
      'grant_type' => 'password',
      'client_id' => $this->client->getClientId(),
      // No client secret sent.
      'username' => $this->user->getAccountName(),
      'password' => $this->user->pass_raw,
      'scope' => $this->scope,
    ];
    $response = $this->post($this->url, $invalid_payload);
    $this->assertEquals(401, $response->getStatusCode());
  }

  /**
   * Test the valid Password grant.
   */
  public function testPasswordGrant(): void {
    $this->client->set('confidential', FALSE);
    $this->client->set('secret', NULL)->save();
    // 1. Test the valid request.
    $valid_payload = [
      'grant_type' => 'password',
      'client_id' => $this->client->getClientId(),
      // This is a public client. PKCE and secrets are tested elsewhere.
      'username' => $this->user->getAccountName(),
      'password' => $this->user->pass_raw,
      'scope' => $this->scope,
    ];
    $response = $this->post($this->url, $valid_payload);
    $this->assertValidTokenResponse($response, TRUE);
    // Repeat the request but pass an obtained access token as a header in
    // order to check the authentication in parallel, which will precede
    // the creation of a new token.
    $parsed = Json::decode((string) $response->getBody());
    $response = $this->post($this->url, $valid_payload, [
      'headers' => ['Authorization' => 'Bearer ' . $parsed['access_token']],
    ]);
    $this->assertValidTokenResponse($response, TRUE);

    // 2. Test the valid request without scopes.
    $payload_no_scope = $valid_payload;
    unset($payload_no_scope['scope']);
    $response = $this->post($this->url, $payload_no_scope);
    $this->assertValidTokenResponse($response, TRUE);

    // 3. Test valid request using HTTP Basic Auth.
    $payload_no_client = $valid_payload;
    unset($payload_no_client['client_id']);
    unset($payload_no_client['client_secret']);
    $response = $this->post($this->url, $payload_no_scope,
      [
        'auth' => [
          $this->client->getClientId(),
          $this->clientSecret,
        ],
      ]
    );
    $this->assertValidTokenResponse($response, TRUE);
  }

  /**
   * Data provider for ::testMissingPasswordGrant.
   */
  public function missingPasswordGrantProvider(): array {
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
      'username' => [
        'username',
        'invalid_request',
        400,
      ],
      'password' => [
        'password',
        'invalid_request',
        400,
      ],
    ];
  }

  /**
   * Test invalid Password grant.
   *
   * @dataProvider missingPasswordGrantProvider
   */
  public function testMissingPasswordGrant(string $key, string $error, int $code): void {
    $valid_payload = [
      'grant_type' => 'password',
      'client_id' => $this->client->getClientId(),
      'client_secret' => $this->clientSecret,
      'username' => $this->user->getAccountName(),
      'password' => $this->user->pass_raw,
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
   * Data provider for ::testInvalidPasswordGrant.
   */
  public function invalidPasswordProvider(): array {
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
      'username' => [
        'username',
        'invalid_grant',
        400,
      ],
      'password' => [
        'password',
        'invalid_grant',
        400,
      ],
    ];
  }

  /**
   * Test invalid Password grant.
   *
   * @dataProvider invalidPasswordProvider
   */
  public function testInvalidPasswordGrant(string $key, string $error, int $code): void {
    $valid_payload = [
      'grant_type' => 'password',
      'client_id' => $this->client->getClientId(),
      'client_secret' => $this->clientSecret,
      'username' => $this->user->getAccountName(),
      'password' => $this->user->pass_raw,
      'scope' => $this->scope,
    ];

    $invalid_payload = $valid_payload;
    $invalid_payload[$key] = $this->randomString();
    $response = $this->post($this->url, $invalid_payload);
    $parsed_response = Json::decode((string) $response->getBody());
    $this->assertSame($error, $parsed_response['error'], sprintf('Correct error code %s', $error));
    $this->assertSame($code, $response->getStatusCode(), sprintf('Correct status code %d', $code));
  }

  /**
   * Test invalid secret on public client.
   */
  public function testInvalidSecretValidatedOnPublicClient(): void {
    $invalid_payload = [
      'grant_type' => 'password',
      'client_id' => $this->client->getClientId(),
      'client_secret' => $this->randomString(),
      'username' => $this->user->getAccountName(),
      'password' => $this->user->pass_raw,
      'scope' => $this->scope,
    ];
    // Test sending an invalid secret; this is a public client, so it's not
    // required, but demonstrates the secret is validated when sent.
    $this->client->set('confidential', FALSE);
    $response = $this->post($this->url, $invalid_payload);
    $this->assertEquals(401, $response->getStatusCode());
  }

}
