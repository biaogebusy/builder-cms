<?php

namespace Drupal\Tests\simple_oauth\Kernel;

use Drupal\Component\Serialization\Json;
use Drupal\TestTools\Random;
use Drupal\Core\Session\AccountInterface;
use Drupal\simple_oauth\Entity\Oauth2Scope;
use Drupal\simple_oauth\Oauth2ScopeInterface;
use Lcobucci\JWT\Encoding\JoseEncoder;
use Lcobucci\JWT\Token\Parser;
use Symfony\Component\HttpFoundation\Request;

/**
 * The client credentials test.
 *
 * @group simple_oauth
 */
class ClientCredentialsTest extends AuthorizedRequestBase {

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    // Client credentials need a valid default user set.
    $this->client->set('user_id', $this->user)->save();
  }

  /**
   * Ensure incorrectly-configured clients without a user are unusable.
   */
  public function testMisconfiguredClient(): void {
    $this->client->set('user_id', NULL)->save();
    $request = Request::create($this->url->toString(), 'POST', [
      'grant_type' => 'client_credentials',
      'client_id' => $this->client->getClientId(),
      'client_secret' => $this->clientSecret,
      'scope' => $this->scope,
    ]);
    $response = $this->httpKernel->handle($request);
    $parsed_response = Json::decode((string) $response->getContent());

    $this->assertEquals(500, $response->getStatusCode());
    $this->assertStringContainsString('Invalid default user for client.', $parsed_response['error_description']);
  }

  /**
   * Test the valid ClientCredentials grant.
   */
  public function testClientCredentialsGrant(): void {
    // 1. Test the valid response.
    $parameters = [
      'grant_type' => 'client_credentials',
      'client_id' => $this->client->getClientId(),
      'client_secret' => $this->clientSecret,
      'scope' => $this->scope,
    ];
    $request = Request::create($this->url->toString(), 'POST', $parameters);
    $response = $this->httpKernel->handle($request);
    $this->assertValidTokenResponse($response);

    // 2. Test default scopes on the client.
    unset($parameters['scope']);
    $request = Request::create($this->url->toString(), 'POST', $parameters);
    $response = $this->httpKernel->handle($request);
    $parsed_response = $this->assertValidTokenResponse($response);
    $this->assertAccessTokenOnResource($parsed_response['access_token']);
  }

  /**
   * Test that JWT tokens include the iss (issuer) claim per RFC 8725.
   */
  public function testJwtIssuerClaim(): void {
    $parameters = [
      'grant_type' => 'client_credentials',
      'client_id' => $this->client->getClientId(),
      'client_secret' => $this->clientSecret,
      'scope' => $this->scope,
    ];
    $request = Request::create($this->url->toString(), 'POST', $parameters);
    $response = $this->httpKernel->handle($request);
    $parsed_response = Json::decode((string) $response->getContent());

    $this->assertNotEmpty($parsed_response['access_token']);

    // Parse the JWT token to verify the iss claim is present.
    $parser = new Parser(new JoseEncoder());
    $token = $parser->parse($parsed_response['access_token']);

    // Verify the iss claim is present per RFC 8725 Section 3.1.
    $this->assertTrue($token->claims()->has('iss'), 'JWT token should include the iss (issuer) claim per RFC 8725 Section 3.1');

    // Verify the issuer is the expected Drupal site URL.
    $issuer = $token->claims()->get('iss');
    $this->assertNotEmpty($issuer, 'Issuer claim should not be empty');
    $this->assertStringStartsWith('http', $issuer, 'Issuer should be a valid URL');
  }

  /**
   * Data provider for ::testMissingClientCredentialsGrant.
   */
  public static function missingClientCredentialsProvider(): array {
    return [
      [
        'grant_type',
        'unsupported_grant_type',
        400,
      ],
      [
        'client_id',
        'invalid_request',
        400,
      ],
      [
        'client_secret',
        'invalid_request',
        400,
      ],
      [
        'scope',
        'invalid_request',
        400,
      ],
    ];
  }

  /**
   * Test invalid ClientCredentials grant.
   *
   * @dataProvider missingClientCredentialsProvider
   */
  public function testMissingClientCredentialsGrant(string $key, string $error, int $code): void {
    $parameters = [
      'grant_type' => 'client_credentials',
      'client_id' => $this->client->getClientId(),
      'client_secret' => $this->clientSecret,
      'scope' => $this->scope,
    ];
    unset($parameters[$key]);
    $this->client->set('scopes', []);
    $this->client->save();
    $request = Request::create($this->url->toString(), 'POST', $parameters);
    $response = $this->httpKernel->handle($request);
    $parsed_response = Json::decode((string) $response->getContent());
    $this->assertSame($error, $parsed_response['error'], sprintf('Correct error code %s', $error));
    $this->assertSame($code, $response->getStatusCode(), sprintf('Correct status code %d', $code));
  }

  /**
   * Data provider for ::testInvalidClientCredentialsGrant.
   */
  public static function invalidClientCredentialsProvider(): array {
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
    ];
  }

  /**
   * Test invalid ClientCredentials grant.
   *
   * @dataProvider invalidClientCredentialsProvider
   */
  public function testInvalidClientCredentialsGrant(string $key, string $error, int $code): void {
    $parameters = [
      'grant_type' => 'client_credentials',
      'client_id' => $this->client->getClientId(),
      'client_secret' => $this->clientSecret,
      'scope' => $this->scope,
    ];
    $parameters[$key] = $this->randomString();
    $request = Request::create($this->url->toString(), 'POST', $parameters);
    $response = $this->httpKernel->handle($request);
    $parsed_response = Json::decode((string) $response->getContent());
    $this->assertSame($error, $parsed_response['error'], sprintf('Correct error code %s', $error));
    $this->assertSame($code, $response->getStatusCode(), sprintf('Correct status code %d', $code));
  }

  /**
   * Data provider for ::testPublicClientCredentialsGrant.
   */
  public static function publicClientCredentialsGrantProvider(): array {
    return [
      // Non-confidential always fails regardless of configured secret.
      'non_confidential_random_secret' => [
        FALSE,
        Random::string(),
        'invalid_client',
        401,
      ],
      'non_confidential_empty_secret' => [
        FALSE,
        '',
        'invalid_client',
        401,
      ],
      'non_confidential_no_secret' => [
        FALSE,
        NULL,
        'invalid_client',
        401,
      ],
      // No configured secret or empty secret always fails.
      'confidential_no_secret' => [
        TRUE,
        NULL,
        'invalid_request',
        400,
      ],
      'confidential_empty_secret' => [
        TRUE,
        '',
        'invalid_request',
        400,
      ],
    ];
  }

  /**
   * Test public client with ClientCredentials grant.
   *
   * The client credentials grant cannot be used with a public client.
   *
   * @dataProvider publicClientCredentialsGrantProvider
   */
  public function testPublicClientCredentialsGrant(bool $confidential, string|null $client_secret, string $error, int $code): void {
    // Configure the client for the test.
    $this->client
      ->set('confidential', $confidential)
      ->set('secret', $client_secret)
      ->save();

    // Prepare client_credentials grant request without a client secret.
    $parameters = [
      'grant_type' => 'client_credentials',
      'client_id' => $this->client->getClientId(),
      'scope' => $this->scope,
      'client_secret' => NULL,
    ];
    $request = Request::create($this->url->toString(), 'POST', $parameters);
    $response = $this->httpKernel->handle($request);
    $parsed_response = Json::decode((string) $response->getContent());
    $this->assertSame($code, $response->getStatusCode(), sprintf('Correct status code %d', $code));
    $this->assertSame($error, $parsed_response['error'], sprintf('Correct error code %s', $error));
  }

  /**
   * Test the not allowed scopes set on the client.
   */
  public function testNotAllowedScopes(): void {
    $not_allowed_scope = Oauth2Scope::create([
      'name' => 'test:scope3',
      'description' => 'Test scope 3 description',
      'grant_types' => [
        'client_credentials' => [
          'status' => TRUE,
          'description' => 'Test scope 3 description client_credentials',
        ],
      ],
      'umbrella' => FALSE,
      'granularity_id' => Oauth2ScopeInterface::GRANULARITY_ROLE,
      'granularity_configuration' => [
        'role' => AccountInterface::AUTHENTICATED_ROLE,
      ],
    ]);
    $not_allowed_scope->save();

    $parameters = [
      'grant_type' => 'client_credentials',
      'client_id' => $this->client->getClientId(),
      'client_secret' => $this->clientSecret,
      'scope' => $not_allowed_scope->getName(),
    ];
    $request = Request::create($this->url->toString(), 'POST', $parameters);
    $response = $this->httpKernel->handle($request);
    $parsed_response = Json::decode((string) $response->getContent());

    $this->assertEquals(400, $response->getStatusCode());
    $this->assertEquals('The requested scope is invalid, unknown, or malformed', $parsed_response['error_description']);

    $parameters['scope'] = "{$this->scope} {$not_allowed_scope->getName()}";
    $request = Request::create($this->url->toString(), 'POST', $parameters);
    $response = $this->httpKernel->handle($request);
    $parsed_response = Json::decode((string) $response->getContent());

    $this->assertEquals(400, $response->getStatusCode());
    $this->assertEquals('The requested scope is invalid, unknown, or malformed', $parsed_response['error_description']);
  }

}
