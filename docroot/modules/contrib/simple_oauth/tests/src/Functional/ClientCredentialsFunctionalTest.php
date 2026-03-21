<?php

namespace Drupal\Tests\simple_oauth\Functional;

use Drupal\Component\Serialization\Json;

/**
 * The client credentials test.
 *
 * @group simple_oauth
 */
class ClientCredentialsFunctionalTest extends TokenBearerFunctionalTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Client credentials also need a valid default user set.
    $this->client->set('user_id', $this->user)->save();
  }

  /**
   * Ensure incorrectly-configured clients without a user are unusable.
   */
  public function testMisconfiguredClient(): void {
    $this->client->set('user_id', NULL)->save();
    // 1. Craft a valid request.
    $valid_payload = [
      'grant_type' => 'client_credentials',
      'client_id' => $this->client->getClientId(),
      'client_secret' => $this->clientSecret,
      'scope' => $this->scope,
    ];
    // 2. The client is misconfigured.
    $response = $this->post($this->url, $valid_payload);
    $parsed_response = Json::decode((string) $response->getBody());
    $this->assertEquals(500, $response->getStatusCode());
    $this->assertStringContainsString('Invalid default user for client.', $parsed_response['message']);
  }

  /**
   * Test the valid ClientCredentials grant.
   */
  public function testClientCredentialsGrant(): void {
    // 1. Test the valid response.
    $valid_payload = [
      'grant_type' => 'client_credentials',
      'client_id' => $this->client->getClientId(),
      'client_secret' => $this->clientSecret,
      'scope' => $this->scope,
    ];
    $response = $this->post($this->url, $valid_payload);
    $this->assertValidTokenResponse($response, FALSE);

    // 2. Test the valid without scopes.
    $payload_no_scope = $valid_payload;
    unset($payload_no_scope['scope']);
    $response = $this->post($this->url, $payload_no_scope);
    $this->assertValidTokenResponse($response, FALSE);

  }

  /**
   * Data provider for ::testMissingClientCredentialsGrant.
   */
  public function missingClientCredentialsProvider(): array {
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
    ];
  }

  /**
   * Test invalid ClientCredentials grant.
   *
   * @dataProvider missingClientCredentialsProvider
   */
  public function testMissingClientCredentialsGrant(string $key, string $error, int $code): void {
    $valid_payload = [
      'grant_type' => 'client_credentials',
      'client_id' => $this->client->getClientId(),
      'client_secret' => $this->clientSecret,
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
   * Data provider for ::testInvalidClientCredentialsGrant.
   */
  public function invalidClientCredentialsProvider(): array {
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
    ];
  }
  /**
   * Test invalid ClientCredentials grant.
   *
   * @dataProvider invalidClientCredentialsProvider
   */
  public function testInvalidClientCredentialsGrant(string $key, string $error, int $code): void {
    $valid_payload = [
      'grant_type' => 'client_credentials',
      'client_id' => $this->client->getClientId(),
      'client_secret' => $this->clientSecret,
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
