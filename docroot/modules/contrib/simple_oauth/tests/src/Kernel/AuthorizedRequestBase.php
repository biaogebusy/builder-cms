<?php

namespace Drupal\Tests\simple_oauth\Kernel;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Drupal\KernelTests\Core\Entity\EntityKernelTestBase;
use Drupal\Tests\simple_oauth\Functional\SimpleOauthTestTrait;
use Drupal\consumers\Entity\Consumer;
use Drupal\simple_oauth\Entity\Oauth2Scope;
use Drupal\simple_oauth\Oauth2ScopeInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class RequestBase.
 *
 * Base class that handles common logic and config for the authorized requests.
 *
 * @package Drupal\Tests\simple_oauth\Kernel
 */
abstract class AuthorizedRequestBase extends EntityKernelTestBase {

  use SimpleOauthTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'consumers',
    'file',
    'image',
    'options',
    'serialization',
    'system',
    'simple_oauth',
    'simple_oauth_test',
    'user',
  ];

  /**
   * The user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $user;

  /**
   * The redirect URI.
   *
   * @var string
   */
  protected $redirectUri;

  /**
   * The request scope.
   *
   * @var string
   */
  protected $scope;

  /**
   * The client.
   *
   * @var \Drupal\consumers\Entity\Consumer
   */
  protected $client;

  /**
   * The client secret.
   *
   * @var string
   */
  protected $clientSecret;

  /**
   * The URL.
   *
   * @var \Drupal\Core\Url
   */
  protected $url;

  /**
   * The kernel.
   *
   * @var \Symfony\Component\HttpKernel\HttpKernelInterface
   */
  protected $httpKernel;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('consumer');
    $this->installEntitySchema('oauth2_token');
    $this->installEntitySchema('user');
    $this->installConfig(['user']);
    $this->installConfig(['simple_oauth']);

    mkdir($this->siteDirectory . '/keys', 0775);
    $public_key_path = "{$this->siteDirectory}/keys/public.key";
    $private_key_path = "{$this->siteDirectory}/keys/private.key";

    file_put_contents($public_key_path, $this->publicKey);
    file_put_contents($private_key_path, $this->privateKey);
    chmod($public_key_path, 0660);
    chmod($private_key_path, 0660);

    $settings = $this->config('simple_oauth.settings');
    $settings->set('public_key', $public_key_path);
    $settings->set('private_key', $private_key_path);
    $settings->save();

    $this->user = $this->drupalCreateUser();

    $this->redirectUri = Url::fromRoute('oauth2_token.test_token', [], [
      'absolute' => TRUE,
    ])->toString();

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
        'refresh_token' => [
          'status' => TRUE,
          'description' => 'Test scope 1 description refresh_token',
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
        'refresh_token' => [
          'status' => TRUE,
          'description' => 'Test scope 2 description refresh_token',
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
    $this->scope = "{$scope_1->getName()} {$scope_2->getName()}";

    $this->clientSecret = $this->randomString();

    $this->client = Consumer::create([
      'client_id' => 'test_client',
      'is_default' => TRUE,
      'label' => 'test',
      'grant_types' => [
        'authorization_code',
        'client_credentials',
        'refresh_token',
      ],
      'scopes' => [$scope_1->id(), $scope_2->id()],
      'secret' => $this->clientSecret,
      'redirect' => [$this->redirectUri],
    ]);
    $this->client->save();
    $this->url = Url::fromRoute('oauth2_token.token');
    $this->httpKernel = $this->container->get('http_kernel');
  }

  /**
   * Validates a valid token response.
   *
   * @param \Symfony\Component\HttpFoundation\Response $response
   *   The response object.
   * @param bool $has_refresh
   *   TRUE if the response should return a refresh token. FALSE otherwise.
   *
   * @return array
   *   An array representing the response of "/oauth/token".
   */
  protected function assertValidTokenResponse(Response $response, bool $has_refresh = FALSE): array {
    $this->assertEquals(200, $response->getStatusCode());
    $parsed_response = Json::decode((string) $response->getContent());
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
   *
   * @throws \Exception
   */
  protected function assertAccessTokenOnResource(string $access_token): void {
    $resource_path = Url::fromRoute('oauth2_resource.test')->toString();
    $request = Request::create($resource_path);
    $request->headers->add(['Authorization' => "Bearer {$access_token}"]);
    $response = $this->httpKernel->handle($request);
    $this->assertEquals(200, $response->getStatusCode());
  }

}
