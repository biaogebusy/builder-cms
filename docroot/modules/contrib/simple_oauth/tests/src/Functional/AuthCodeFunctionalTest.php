<?php

namespace Drupal\Tests\simple_oauth\Functional;

use Drupal\Core\Url;
use Drupal\simple_oauth\Entity\Oauth2Scope;
use Drupal\simple_oauth\Oauth2ScopeInterface;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;
use Drupal\user\UserInterface;
use GuzzleHttp\Psr7\Query;
use Psr\Http\Message\ResponseInterface;

/**
 * The auth code test.
 *
 * @group simple_oauth
 */
class AuthCodeFunctionalTest extends TokenBearerFunctionalTestBase {

  /**
   * The authorize URL.
   *
   * @var \Drupal\Core\Url
   */
  protected Url $authorizeUrl;

  /**
   * An extra scope for testing.
   *
   * @var \Drupal\simple_oauth\Oauth2ScopeInterface
   */
  protected Oauth2ScopeInterface $extraScope;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->authorizeUrl = Url::fromRoute('oauth2_token.authorize');

    $this->grantPermissions(Role::load(RoleInterface::AUTHENTICATED_ID), [
      'grant simple_oauth codes',
      'access content',
    ]);

    $this->extraScope = Oauth2Scope::create([
      'name' => 'test:scope3',
      'description' => 'Test scope 3 description',
      'grant_types' => [
        'authorization_code' => [
          'status' => TRUE,
        ],
      ],
      'umbrella' => TRUE,
    ]);
    $this->extraScope->save();
  }

  /**
   * Test the valid AuthCode grant with a public client.
   */
  public function testPublicAuthCodeGrant(): void {
    $this->client->set('confidential', FALSE)->save();
    $valid_params = [
      'response_type' => 'code',
      'client_id' => $this->client->getClientId(),
      'scope' => $this->scope,
      'redirect_uri' => $this->redirectUri,
    ];
    // 1. Anonymous request invites the user to log in.
    $this->drupalGet($this->authorizeUrl->toString(), [
      'query' => $valid_params,
    ]);
    $assert_session = $this->assertSession();
    $assert_session->buttonExists('Log in');

    // 2. Log the user in and try again.
    $this->drupalLogin($this->user);
    $this->drupalGet($this->authorizeUrl->toString(), [
      'query' => $valid_params,
    ]);
    $this->assertGrantForm();

    // 3. Grant access by submitting the form and get the code back.
    $this->submitForm([], 'Allow');

    // Store the code for the second part of the flow.
    $code = $this->getAndValidateCodeFromResponse();

    // 4. Send the code to get the access token.
    $response = $this->postGrantedCodeWithScopes($code, $this->scope, FALSE);
    $parsed_response = $this->assertValidTokenResponse($response, TRUE);

    // 5. Ensure codes cannot be re-used.
    $response = $this->postGrantedCodeWithScopes($code, $this->scope, FALSE);
    $this->assertEquals(400, $response->getStatusCode());

    // 6. Test access token.
    $this->assertAccessTokenOnResource($parsed_response['access_token']);
  }

  /**
   * Test the automatic authorization when enabled on client.
   */
  public function testAutomaticAuthorization(): void {
    $this->client->set('automatic_authorization', TRUE);
    $this->client->save();

    $valid_params = [
      'response_type' => 'code',
      'client_id' => $this->client->getClientId(),
      'scope' => $this->scope,
      'redirect_uri' => $this->redirectUri,
    ];
    // 1. Anonymous request invites the user to log in.
    $this->drupalGet($this->authorizeUrl->toString(), [
      'query' => $valid_params,
    ]);
    $assert_session = $this->assertSession();
    $assert_session->buttonExists('Log in');

    // 2. Log the user in and try again. This time we should get a code
    // immediately without granting, because the consumer is not 3rd party.
    $this->drupalLogin($this->user);
    $this->drupalGet($this->authorizeUrl->toString(), [
      'query' => $valid_params,
    ]);
    // Store the code for the second part of the flow.
    $code = $this->getAndValidateCodeFromResponse();

    // 3. Send the code to get the access token, regardless of the scopes, since
    // the consumer has automatic authorization enabled.
    $response = $this->postGrantedCodeWithScopes(
      $code,
      $this->scope . ' ' . $this->extraScope->id()
    );
    $parsed_response = $this->assertValidTokenResponse($response, TRUE);

    // 4. Test access token.
    $this->assertAccessTokenOnResource($parsed_response['access_token']);
  }

  /**
   * Tests functionality remember approval, which is enabled by default.
   */
  public function testDefaultEnabledRememberApproval(): void {
    $valid_params = [
      'response_type' => 'code',
      'client_id' => $this->client->getClientId(),
      'scope' => $this->scope,
      'redirect_uri' => $this->redirectUri,
    ];
    // 1. Anonymous request invites the user to log in.
    $this->drupalGet($this->authorizeUrl->toString(), [
      'query' => $valid_params,
    ]);
    $assert_session = $this->assertSession();
    $assert_session->buttonExists('Log in');

    // 2. Log the user in and try again.
    $this->drupalLogin($this->user);
    $this->drupalGet($this->authorizeUrl->toString(), [
      'query' => $valid_params,
    ]);
    $this->assertGrantForm();

    // 3. Grant access by submitting the form and get the token back.
    $this->submitForm([], 'Allow');

    // Store the code for the second part of the flow.
    $code = $this->getAndValidateCodeFromResponse();

    // 4. Send the code to get the access token.
    $response = $this->postGrantedCodeWithScopes($code, $this->scope);
    $parsed_response = $this->assertValidTokenResponse($response, TRUE);

    // 5. Ensure codes cannot be re-used.
    $response = $this->postGrantedCodeWithScopes($code, $this->scope);
    $this->assertEquals(400, $response->getStatusCode());

    // 6. Test access token.
    $this->assertAccessTokenOnResource($parsed_response['access_token']);
  }

  /**
   * Test confidential clients enforce a client secret.
   */
  public function testConfidentialAuthCodeGrant(): void {
    $this->client->set('confidential', TRUE)->save();
    $valid_params = [
      'response_type' => 'code',
      'client_id' => $this->client->getClientId(),
      'client_secret' => $this->clientSecret,
      'scope' => $this->scope,
      'redirect_uri' => $this->redirectUri,
    ];
    // 1. Anonymous request invites the user to log in.
    $this->drupalGet($this->authorizeUrl->toString(), [
      'query' => $valid_params,
    ]);
    $assert_session = $this->assertSession();
    $assert_session->buttonExists('Log in');

    // 2. Log the user in and try again.
    $this->drupalLogin($this->user);
    $this->drupalGet($this->authorizeUrl->toString(), [
      'query' => $valid_params,
    ]);
    $this->assertGrantForm();

    // 3. Grant access by submitting the form and get the code back.
    $this->submitForm([], 'Allow');

    // Store the code for the second part of the flow.
    $code = $this->getAndValidateCodeFromResponse();

    // 4. Send a request without a client secret.
    $response = $this->postGrantedCodeWithScopes($code, $this->scope, FALSE);
    $this->assertEquals(400, $response->getStatusCode());

    // 5. Confidential clients still work when passing a secret.
    $response = $this->postGrantedCodeWithScopes($code, $this->scope);
    $this->assertValidTokenResponse($response, TRUE);

    // Do a second authorize request, the client is now remembered and the user
    // does not need to confirm again.
    $this->drupalGet($this->authorizeUrl->toString(), [
      'query' => $valid_params,
    ]);

    $code = $this->getAndValidateCodeFromResponse();

    $response = $this->postGrantedCodeWithScopes($code, $this->scope);
    $this->assertValidTokenResponse($response, TRUE);

    // Do a third request with an additional scope.
    $valid_params['scope'] .= ' ' . $this->extraScope->getName();
    $this->drupalGet($this->authorizeUrl->toString(), [
      'query' => $valid_params,
    ]);

    $this->assertGrantForm();
    $this->assertSession()->pageTextContains($this->extraScope->getDescription());
    $this->submitForm([], 'Allow');

    $code = $this->getAndValidateCodeFromResponse();

    $response = $this->postGrantedCodeWithScopes(
      $code, $valid_params['scope']
    );
    $this->assertValidTokenResponse($response, TRUE);

    // Do another request with the additional scope, this scope is now
    // remembered too.
    $this->drupalGet($this->authorizeUrl->toString(), [
      'query' => $valid_params,
    ]);
    $code = $this->getAndValidateCodeFromResponse();

    $response = $this->postGrantedCodeWithScopes(
      $code, $valid_params['scope']
    );
    $this->assertValidTokenResponse($response, TRUE);

    // Disable remember approval feature, make sure that the redirect doesn't
    // happen automatically anymore.
    $this->client->set('remember_approval', FALSE);
    $this->client->save();

    $this->drupalGet($this->authorizeUrl->toString(), [
      'query' => $valid_params,
    ]);

    $this->assertGrantForm();
  }

  /**
   * Test the AuthCode grant with PKCE.
   */
  public function testClientAuthCodeGrantWithPkce(): void {
    $this->client->set('pkce', TRUE);
    $this->client->set('confidential', FALSE);
    $this->client->save();

    // For PKCE flow we need a code verifier and a code challenge.
    // @see https://tools.ietf.org/html/rfc7636 for details.
    $code_verifier = self::base64urlencode(random_bytes(64));
    $code_challenge = self::base64urlencode(hash('sha256', $code_verifier, TRUE));

    $valid_params = [
      'response_type' => 'code',
      'client_id' => $this->client->getClientId(),
      'code_challenge' => $code_challenge,
      'code_challenge_method' => 'S256',
      'scope' => $this->scope,
      'redirect_uri' => $this->redirectUri,
    ];

    // 1. Anonymous request redirect to log in.
    $this->drupalGet($this->authorizeUrl->toString(), [
      'query' => $valid_params,
    ]);
    $assert_session = $this->assertSession();
    $assert_session->buttonExists('Log in');

    // 2. Logged in user gets the grant form.
    $this->drupalLogin($this->user);
    $this->drupalGet($this->authorizeUrl->toString(), [
      'query' => $valid_params,
    ]);
    $this->assertGrantForm();

    // 3. Grant access by submitting the form.
    $this->submitForm([], 'Allow');

    // Store the code for the second part of the flow.
    $code = $this->getAndValidateCodeFromResponse();

    // Request the access and refresh token.
    $valid_payload = [
      'grant_type' => 'authorization_code',
      'client_id' => $this->client->getClientId(),
      'code_verifier' => $code_verifier,
      'scope' => $this->scope . ' ' . $this->extraScope->getName(),
      'code' => $code,
      'redirect_uri' => $this->redirectUri,
    ];
    $response = $this->post($this->url, $valid_payload);
    $parsed_response = $this->assertValidTokenResponse($response, TRUE);

    // Test access token.
    $this->assertAccessTokenOnResource($parsed_response['access_token']);
  }

  /**
   * Test the optional redirect uri.
   */
  public function testOptionalRedirectUri(): void {
    // Not providing redirect uri, this means the redirect uri set on the client
    // will be used.
    $valid_params = [
      'response_type' => 'code',
      'client_id' => $this->client->getClientId(),
      'client_secret' => $this->clientSecret,
      'scope' => $this->scope,
    ];
    // 1. Anonymous request invites the user to log in.
    $this->drupalGet($this->authorizeUrl->toString(), [
      'query' => $valid_params,
    ]);
    $assert_session = $this->assertSession();
    $assert_session->buttonExists('Log in');

    // 2. Log the user in and try again.
    $this->drupalLogin($this->user);
    $this->drupalGet($this->authorizeUrl->toString(), [
      'query' => $valid_params,
    ]);
    $this->assertGrantForm();

    // 3. Deny access by submitting the form.
    $this->submitForm([], 'Deny');
    $query = $this->getQueryAndValidateRedirect();
    $this->assertArrayHasKey('error', $query);
    $this->assertEquals('access_denied', $query['error']);

    // Perform same request, but this time allow grant.
    $this->drupalGet($this->authorizeUrl->toString(), [
      'query' => $valid_params,
    ]);
    $this->submitForm([], 'Allow');
    $this->getAndValidateCodeFromResponse();

    // Set additional redirect uri on the client, and perform again request
    // with redirect uri.
    $this->client->set('redirect', [
      'mobile://test',
      $this->redirectUri,
    ]);
    $this->client->save();
    $valid_params['redirect_uri'] = $this->redirectUri;
    // Adding additional scope, because the 'remember approval' is enabled.
    $valid_params['scope'] .= " {$this->extraScope->getName()}";
    $this->drupalGet($this->authorizeUrl->toString(), [
      'query' => $valid_params,
    ]);
    $this->submitForm([], 'Allow');
    $this->getAndValidateCodeFromResponse();
  }

  /**
   * Test authorization code grant with default scopes.
   *
   * Tests when no scope parameter is provided.
   */
  public function testAuthCodeWithDefaultScopes(): void {
    // Create a scope with permission-based granularity for testing.
    $defaultScope = Oauth2Scope::create([
      'name' => 'test:default',
      'description' => 'Test default scope',
      'grant_types' => [
        'authorization_code' => [
          'status' => TRUE,
        ],
      ],
      'umbrella' => FALSE,
      'granularity_id' => Oauth2ScopeInterface::GRANULARITY_PERMISSION,
      'granularity_configuration' => [
        'permission' => 'access content',
      ],
    ]);
    $defaultScope->save();

    // Configure default scopes on the consumer for authorization_code grant.
    $this->client->set('authorization_code_scopes', [$defaultScope->id()]);
    $this->client->save();

    $valid_params = [
      'response_type' => 'code',
      'client_id' => $this->client->getClientId(),
      'redirect_uri' => $this->redirectUri,
    ];

    // Log in the user.
    $this->drupalLogin($this->user);

    // Request authorization without providing a scope parameter.
    $this->drupalGet($this->authorizeUrl->toString(), [
      'query' => $valid_params,
    ]);
    $this->assertGrantForm();

    // Grant access and obtain the authorization code.
    $this->submitForm([], 'Allow');
    $code = $this->getAndValidateCodeFromResponse();

    // Exchange authorization code for access token.
    $response = $this->postGrantedCodeWithScopes($code, '');
    $parsed_response = $this->assertValidTokenResponse($response, TRUE);

    // Verify the access token grants access to protected resources.
    $this->assertAccessTokenOnResource($parsed_response['access_token']);
  }

  /**
   * Test authorization fails when no scopes provided.
   *
   * Tests when no scope parameter or default scopes are provided.
   */
  public function testAuthCodeRequiresScopeOrDefaults(): void {
    // Ensure no default scopes are configured on the consumer.
    $this->client->set('authorization_code_scopes', []);
    $this->client->save();

    $valid_params = [
      'response_type' => 'code',
      'client_id' => $this->client->getClientId(),
      'redirect_uri' => $this->redirectUri,
    ];

    // Log in the user.
    $this->drupalLogin($this->user);

    // Request authorization without providing scope parameter.
    $this->drupalGet($this->authorizeUrl->toString(), [
      'query' => $valid_params,
    ]);

    // Verify the request fails with a 400 Bad Request error.
    // The OAuth server requires at least one scope to be specified either
    // via the request parameter or as a default scope on the consumer.
    $this->assertSession()->statusCodeEquals(400);
  }

  /**
   * Test that scope fields filter by grant type.
   */
  public function testScopeFieldFilteringByGrantType(): void {
    // Create scopes with different grant type configurations.
    $authCodeScope = Oauth2Scope::create([
      'name' => 'test:auth_code_filter',
      'grant_types' => [
        'authorization_code' => ['status' => TRUE],
        'client_credentials' => ['status' => FALSE],
      ],
      'granularity_id' => Oauth2ScopeInterface::GRANULARITY_PERMISSION,
      'granularity_configuration' => ['permission' => 'access content'],
    ]);
    $authCodeScope->save();

    $clientCredsScope = Oauth2Scope::create([
      'name' => 'test:client_creds_filter',
      'grant_types' => [
        'authorization_code' => ['status' => FALSE],
        'client_credentials' => ['status' => TRUE],
      ],
      'granularity_id' => Oauth2ScopeInterface::GRANULARITY_PERMISSION,
      'granularity_configuration' => ['permission' => 'access content'],
    ]);
    $clientCredsScope->save();

    // Test authorization_code_scopes field only shows auth code scopes.
    $this->client->set('authorization_code_scopes', [$authCodeScope->id()]);
    $auth_options = $this->client->get('authorization_code_scopes')->first()->getPossibleOptions();
    $this->assertArrayHasKey($authCodeScope->id(), $auth_options);
    $this->assertArrayNotHasKey($clientCredsScope->id(), $auth_options);

    // Test scopes field only shows client credentials scopes.
    $this->client->set('scopes', [$clientCredsScope->id()]);
    $creds_options = $this->client->get('scopes')->first()->getPossibleOptions();
    $this->assertArrayHasKey($clientCredsScope->id(), $creds_options);
    $this->assertArrayNotHasKey($authCodeScope->id(), $creds_options);
  }

  /**
   * Test registration with one time login.
   */
  public function testRegistrationWithOneTimeLogin(): void {
    // Allow registration with administrator approval.
    $this->config('user.settings')->set('register', UserInterface::REGISTER_VISITORS_ADMINISTRATIVE_APPROVAL)->save();
    $valid_params = [
      'response_type' => 'code',
      'client_id' => $this->client->getClientId(),
      'client_secret' => $this->clientSecret,
      'scope' => $this->scope,
      'redirect_uri' => $this->redirectUri,
    ];

    // 1. Register user.
    $destination_url = $this->authorizeUrl->setOption('query', $valid_params)->toString();
    $this->drupalGet('user/register', [
      'query' => [
        'destination' => $destination_url,
      ],
    ]);
    $edit['name'] = $this->randomMachineName();
    $edit['mail'] = $edit['name'] . '@example.com';
    $this->submitForm($edit, 'Create new account');

    // 2. Approve user.
    $this->container->get('entity_type.manager')->getStorage('user')->resetCache();
    $user_storage = $this->container->get('entity_type.manager')->getStorage('user');
    /** @var \Drupal\user\UserInterface[] $accounts */
    $accounts = $user_storage->loadByProperties($edit);
    $new_user = reset($accounts);
    // Unblock user.
    $new_user
      ->set('status', TRUE)
      ->save();

    // 3. Login via the one time login.
    $reset_url = user_pass_reset_url($new_user);
    $this->drupalGet($reset_url);
    $this->submitForm([], 'Log in');

    // 4. After saving the user, authorization form will be available.
    $this->submitForm([
      'pass[pass1]' => $pass = $this->randomString(),
      'pass[pass2]' => $pass,
    ], 'Save');
    $this->assertGrantForm();
  }

  /**
   * Test permission scope on custom role.
   */
  public function testPermissionScopeOnCustomRole(): void {
    // Revoke 'access content' on the authenticated role and grant it on the
    // custom role.
    $auth_role = Role::load(RoleInterface::AUTHENTICATED_ID);
    $auth_role->revokePermission('access content');
    $auth_role->save();
    $role_id = $this->createRole(['access content'], 'custom_role', 'Custom role');

    $account = $this->createUser([], NULL, FALSE, ['roles' => [$role_id]]);
    $this->drupalLogin($account);

    $this->client->set('automatic_authorization', TRUE);
    $this->client->save();

    $valid_params = [
      'response_type' => 'code',
      'client_id' => $this->client->getClientId(),
      'redirect_uri' => $this->redirectUri,
      'scope' => $this->scope,
    ];

    // Request authorization without providing scope parameter.
    $this->drupalGet($this->authorizeUrl->toString(), [
      'query' => $valid_params,
    ]);

    // Store the code for the second part of the flow.
    $code = $this->getAndValidateCodeFromResponse();

    // Send the code to get the access token, regardless of the scopes, since
    // the consumer has automatic authorization enabled.
    $response = $this->postGrantedCodeWithScopes(
      $code,
      $this->scope . ' ' . $this->extraScope->id()
    );
    $parsed_response = $this->assertValidTokenResponse($response, TRUE);

    // Both roles are assigned on the user, we expect access.
    $this->assertAccessTokenOnResource($parsed_response['access_token']);

    // Revoke permission on the custom role.
    $custom_role = Role::load($role_id);
    $custom_role->revokePermission('access content');
    $custom_role->save();

    // Assert again.
    $this->assertAccessTokenOnResource($parsed_response['access_token'], 403);
  }

  /**
   * Helper function to assert the current page is a valid grant form.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  protected function assertGrantForm(): void {
    $assert_session = $this->assertSession();
    $assert_session->statusCodeEquals(200);
    $assert_session->titleEquals('Grant Access to Client | Drupal');
    $assert_session->buttonExists('Allow');
    $assert_session->buttonExists('Deny');
  }

  /**
   * Get the code in the response after granting access to scopes.
   *
   * @return string
   *   The code.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  protected function getAndValidateCodeFromResponse(): string {
    $query = $this->getQueryAndValidateRedirect();
    $this->assertArrayHasKey('code', $query);
    return $query['code'];
  }

  /**
   * Get the parsed query and validate the redirect.
   *
   * @return array
   *   The parsed URL query.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  protected function getQueryAndValidateRedirect(): array {
    $assert_session = $this->assertSession();
    $session = $this->getSession();
    $assert_session->statusCodeEquals(200);
    $parsed_url = parse_url($session->getCurrentUrl());
    $redirect_url = "{$parsed_url['scheme']}://{$parsed_url['host']}";
    if (isset($parsed_url['port'])) {
      $redirect_url .= ':' . $parsed_url['port'];
    }
    $redirect_url .= $parsed_url['path'];
    $this->assertEquals($this->redirectUri, $redirect_url);
    return Query::parse($parsed_url['query']);
  }

  /**
   * Posts the code and requests access to the scopes.
   *
   * @param string $code
   *   The granted code.
   * @param string $scopes
   *   The list of scopes to request access to.
   * @param bool $send_secret
   *   Whether to send the client secret.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   The response.
   */
  protected function postGrantedCodeWithScopes(string $code, string $scopes, bool $send_secret = TRUE): ResponseInterface {
    $valid_payload = [
      'grant_type' => 'authorization_code',
      'client_id' => $this->client->getClientId(),
      'code' => $code,
      'scope' => $scopes,
      'redirect_uri' => $this->redirectUri,
    ];
    if ($send_secret) {
      $valid_payload['client_secret'] = $this->clientSecret;
    }
    return $this->post($this->url, $valid_payload);
  }

}
