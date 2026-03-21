<?php

namespace Drupal\Tests\simple_oauth\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\consumers\Entity\Consumer;
use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;
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
    'node',
    'serialization',
    'simple_oauth',
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
   * @var \Drupal\consumers\Entity\ConsumerInterface
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
   * Additional roles used during tests.
   *
   * @var \Drupal\user\RoleInterface[]
   */
  protected $additionalRoles;

  /**
   * The request scope.
   *
   * @var string
   */
  protected $scope;

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

    $client_role = Role::create([
      'id' => $this->randomMachineName(),
      'label' => $this->getRandomGenerator()->word(5),
      'is_admin' => FALSE,
    ]);
    $client_role->save();

    $this->additionalRoles = [];
    for ($i = 0; $i < mt_rand(1, 3); $i++) {
      $role = Role::create([
        'id' => $this->randomMachineName(),
        'label' => $this->getRandomGenerator()->word(5),
        'is_admin' => FALSE,
      ]);
      $role->save();
      $this->additionalRoles[] = $role;
    }

    $this->clientSecret = $this->randomString();

    $this->client = Consumer::create([
      'owner_id' => '',
      'label' => $this->randomMachineName(),
      'client_id' => $this->randomString(),
      'secret' => $this->clientSecret,
      'confidential' => TRUE,
      'third_party' => TRUE,
      'roles' => [['target_id' => $client_role->id()]],
    ]);
    $this->client->save();

    $this->user = $this->drupalCreateUser();
    $this->grantPermissions(Role::load(RoleInterface::ANONYMOUS_ID), [
      'access content',
      'debug simple_oauth tokens',
    ]);
    $this->grantPermissions(Role::load(RoleInterface::AUTHENTICATED_ID), [
      'access content',
      'debug simple_oauth tokens',
    ]);

    $this->setUpKeys();

    $num_roles = mt_rand(1, count($this->additionalRoles));
    $requested_roles = array_slice($this->additionalRoles, 0, $num_roles);
    $scopes = array_map(function (RoleInterface $role) {
      return $role->id();
    }, $requested_roles);
    $this->scope = implode(' ', $scopes);

    drupal_flush_all_caches();
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
    $expiration = $this->config('simple_oauth.settings')
      ->get('access_token_expiration');
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

}
