<?php

namespace Drupal\Tests\simple_oauth\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use Drupal\consumers\Entity\Consumer;
use Drupal\simple_oauth\Entity\Oauth2Scope;
use Drupal\simple_oauth\Oauth2ScopeInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Class TokenBearerFunctionalTestBase.
 *
 * Base class that handles common logic and config for the token tests.
 *
 * @package Drupal\Tests\simple_oauth\Functional
 */
abstract class TokenBearerFunctionalTestBase extends BrowserTestBase {

  use RequestHelperTrait;
  use SimpleOauthTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'image',
    'options',
    'serialization',
    'simple_oauth_test',
    'text',
    'user',
  ];

  /**
   * The URL.
   *
   * @var \Drupal\Core\Url
   */
  protected Url $url;

  /**
   * The client.
   *
   * @var \Drupal\consumers\Entity\Consumer
   */
  protected $client;

  /**
   * The user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  /**
   * The client secret.
   *
   * @var string
   */
  protected string $clientSecret;

  /**
   * The HTTP client to make requests.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * The request scope.
   *
   * @var string
   */
  protected string $scope;

  /**
   * The redirect URI.
   *
   * @var string
   */
  protected string $redirectUri;

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->url = Url::fromRoute('oauth2_token.token');

    // Set up a HTTP client that accepts relative URLs.
    $this->httpClient = $this->container->get('http_client_factory')
      ->fromOptions(['base_uri' => $this->baseUrl]);

    $this->clientSecret = $this->randomString();

    $this->redirectUri = Url::fromRoute('oauth2_token.test_token', [], [
      'absolute' => TRUE,
    ])->toString();

    $this->user = $this->drupalCreateUser();

    $this->setUpKeys();

    $scope_1 = Oauth2Scope::create([
      'name' => 'test:scope1',
      'description' => 'Test scope 1 description',
      'grant_types' => [
        'authorization_code' => [
          'status' => TRUE,
          'description' => 'Test scope 1 description authorization_code',
        ],
        'client_credentials' => [
          'status' => TRUE,
          'description' => 'Test scope 1 description client_credentials',
        ],
      ],
      'umbrella' => FALSE,
      'granularity_id' => Oauth2ScopeInterface::GRANULARITY_PERMISSION,
      'granularity_configuration' => [
        'permission' => 'access content',
      ],
    ]);
    $scope_2 = Oauth2Scope::create([
      'name' => 'test:scope2',
      'description' => 'Test scope 2 description',
      'grant_types' => [
        'authorization_code' => [
          'status' => TRUE,
          'description' => 'Test scope 2 description authorization_code',
        ],
        'client_credentials' => [
          'status' => TRUE,
          'description' => 'Test scope 2 description client_credentials',
        ],
      ],
      'umbrella' => FALSE,
      'granularity_id' => Oauth2ScopeInterface::GRANULARITY_PERMISSION,
      'granularity_configuration' => [
        'permission' => 'debug simple_oauth tokens',
      ],
    ]);
    $scope_1->save();
    $scope_2->save();

    $this->client = Consumer::create([
      'client_id' => $this->randomString(),
      'label' => $this->getRandomGenerator()->name(),
      'secret' => $this->clientSecret,
      'grant_types' => [
        'authorization_code',
        'client_credentials',
        'refresh_token',
      ],
      'redirect' => [$this->redirectUri],
      'scopes' => [$scope_1->id(), $scope_2->id()],
    ]);
    $this->client->save();

    $this->scope = "{$scope_1->getName()} {$scope_2->getName()}";
  }

  /**
   * Validates a valid token response.
   *
   * @param \Psr\Http\Message\ResponseInterface $response
   *   The response object.
   * @param bool $has_refresh
   *   TRUE if the response should return a refresh token. FALSE otherwise.
   *
   * @return array
   *   An array representing the response of "/oauth/token".
   */
  protected function assertValidTokenResponse(ResponseInterface $response, bool $has_refresh = FALSE): array {
    $this->assertEquals(200, $response->getStatusCode());
    $parsed_response = Json::decode((string) $response->getBody());
    $this->assertSame('Bearer', $parsed_response['token_type']);
    $expiration = $this->client->get('access_token_expiration')->value;
    $this->assertLessThanOrEqual($expiration, $parsed_response['expires_in']);
    $this->assertGreaterThanOrEqual($expiration - 10, $parsed_response['expires_in']);
    $this->assertNotEmpty($parsed_response['access_token']);
    if ($has_refresh) {
      $this->assertNotEmpty($parsed_response['refresh_token']);
    }
    else {
      $this->assertFalse(isset($parsed_response['refresh_token']));
    }

    return $parsed_response;
  }

  /**
   * Validates access token on test resource.
   *
   * @param string $access_token
   *   The access token.
   * @param int $status_code
   *   The expected status code.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  protected function assertAccessTokenOnResource(string $access_token, int $status_code = 200): void {
    $resource_path = Url::fromRoute('oauth2_resource.test')->toString();
    $this->drupalGet($resource_path, [], [
      'Authorization' => "Bearer {$access_token}",
    ]);
    $this->assertSession()->statusCodeEquals($status_code);
  }

}
