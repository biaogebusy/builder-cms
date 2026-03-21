<?php

namespace Drupal\Tests\simple_oauth\Functional;

use GuzzleHttp\Psr7\Query;
use Drupal\Core\Url;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;
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
   * The redirect URI.
   *
   * @var string
   */
  protected string $redirectUri;

  /**
   * An extra role for testing.
   *
   * @var \Drupal\user\RoleInterface
   */
  protected RoleInterface $extraRole;

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['simple_oauth_test'];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->redirectUri = Url::fromRoute('oauth2_token.test_token', [], [
      'absolute' => TRUE,
    ])->toString();
    $this->client->set('redirect', $this->redirectUri);
    $this->client->set('description', $this->getRandomGenerator()
      ->paragraphs());
    $this->client->save();
    $this->authorizeUrl = Url::fromRoute('oauth2_token.authorize');
    $this->grantPermissions(Role::load(RoleInterface::AUTHENTICATED_ID), [
      'grant simple_oauth codes',
    ]);
    // Add a scope so we can ensure all tests have at least 2 roles. That way we
    // can test dropping a scope and still have at least one scope.
    $additional_scope = $this->randomMachineName();
    Role::create([
      'id' => $additional_scope,
      'label' => $this->getRandomGenerator()->word(5),
      'is_admin' => FALSE,
    ])->save();
    $this->scope = $this->scope . ' ' . $additional_scope;
    // Add a random scope that is not in the base scopes list to request so we
    // can make extra checks on it.
    $this->extraRole = Role::create([
      'id' => $this->randomMachineName(),
      'label' => $this->getRandomGenerator()->word(5),
      'is_admin' => FALSE,
    ]);
    $this->extraRole->save();
  }

  /**
   * Test the valid AuthCode grant with a public client.
   */
  public function testPublicAuthCodeGrant(): void {
    $this->client->set('confidential', FALSE)->save();
    $valid_params = [
      'response_type' => 'code',
      'client_id' => $this->client->getClientId(),
      // Not sending a client secret.
      'redirect_uri' => Url::fromRoute('oauth2_token.test_token', [], [
        'absolute' => TRUE,
      ])->toString(),
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
    $this->drupalGet($this->authorizeUrl, [
      'query' => $valid_params,
    ]);

    // 3. Grant access by submitting the form and get the code back.
    $this->submitForm([], 'Grant');
    // Store the code for the second part of the flow.
    $code = $this->getAndValidateCodeFromResponse();

    // 4. Send the code to get the access token.
    $response = $this->postGrantedCodeWithScopes($code, $this->scope, FALSE);
    $this->assertValidTokenResponse($response, TRUE);

    // 5. Ensure codes cannot be re-used.
    $response = $this->postGrantedCodeWithScopes($code, $this->scope, FALSE);
    $this->assertEquals(400, $response->getStatusCode());
  }

  /**
   * Test the valid AuthCode grant if the client is non 3rd party.
   */
  public function testNon3rdPartyClientAuthCodeGrant(): void {
    $this->client->set('third_party', FALSE);
    $this->client->save();

    $valid_params = [
      'response_type' => 'code',
      'client_id' => $this->client->getClientId(),
      'redirect_uri' => Url::fromRoute('oauth2_token.test_token', [], [
        'absolute' => TRUE,
      ])->toString(),
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
    // the consumer is trusted.
    $response = $this->postGrantedCodeWithScopes(
      $code,
      $this->scope . ' ' . $this->extraRole->id()
    );
    $this->assertValidTokenResponse($response, TRUE);
  }

  /**
   * Tests the remember client functionality.
   */
  public function testRememberClient(): void {
    $valid_params = [
      'response_type' => 'code',
      'client_id' => $this->client->getClientId(),
      'redirect_uri' => Url::fromRoute('oauth2_token.test_token', [], [
        'absolute' => TRUE,
      ])->toString(),
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
    $this->submitForm([], 'Grant');

    // Store the code for the second part of the flow.
    $code = $this->getAndValidateCodeFromResponse();

    // 4. Send the code to get the access token.
    $response = $this->postGrantedCodeWithScopes($code, $this->scope);
    $this->assertValidTokenResponse($response, TRUE);

    // 5. Ensure codes cannot be re-used.
    $response = $this->postGrantedCodeWithScopes($code, $this->scope);
    $this->assertEquals(400, $response->getStatusCode());
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
      'redirect_uri' => Url::fromRoute('oauth2_token.test_token', [], [
        'absolute' => TRUE,
      ])->toString(),
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
    $this->submitForm([], 'Grant');
    // Store the code for the second part of the flow.
    $code = $this->getAndValidateCodeFromResponse();

    // 4. Send a request without a client secret.
    $response = $this->postGrantedCodeWithScopes($code, $this->scope, FALSE);
    $this->assertEquals(401, $response->getStatusCode());

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
    $valid_params['scope'] = $this->extraRole->id();
    $this->drupalGet($this->authorizeUrl->toString(), [
      'query' => $valid_params,
    ]);

    $this->assertGrantForm();
    $this->assertSession()->pageTextContains($this->extraRole->label());
    $this->submitForm([], 'Grant');

    $code = $this->getAndValidateCodeFromResponse();

    $response = $this->postGrantedCodeWithScopes(
      $code, $this->scope . ' ' . $this->extraRole->id()
    );
    $this->assertValidTokenResponse($response, TRUE);

    // Do another request with the additional scope, this scope is now
    // remembered too.
    $valid_params['scope'] = $this->extraRole->id();
    $this->drupalGet($this->authorizeUrl->toString(), [
      'query' => $valid_params,
    ]);
    $code = $this->getAndValidateCodeFromResponse();

    $response = $this->postGrantedCodeWithScopes(
      $code, $this->scope . ' ' . $this->extraRole->id()
    );
    $this->assertValidTokenResponse($response, TRUE);

    // Disable the remember clients feature, make sure that the redirect doesn't
    // happen automatically anymore.
    $this->config('simple_oauth.settings')
      ->set('remember_clients', FALSE)
      ->save();

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
      'redirect_uri' => Url::fromRoute('oauth2_token.test_token', [], [
        'absolute' => TRUE,
      ])->toString(),
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
    $this->submitForm([], 'Grant');

    // Store the code for the second part of the flow.
    $code = $this->getAndValidateCodeFromResponse();

    // Request the access and refresh token.
    $valid_payload = [
      'grant_type' => 'authorization_code',
      'client_id' => $this->client->getClientId(),
      'code_verifier' => $code_verifier,
      'scope' => $this->scope . ' ' . $this->extraRole->id(),
      'code' => $code,
      'redirect_uri' => $this->redirectUri,
    ];
    $response = $this->post($this->url, $valid_payload);
    $this->assertValidTokenResponse($response, TRUE);
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
    $assert_session->buttonExists('Grant');
    $assert_session->responseContains('Permissions');
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
    $assert_session = $this->assertSession();
    $session = $this->getSession();
    $assert_session->statusCodeEquals(200);
    $parsed_url = parse_url($session->getCurrentUrl());
    $parsed_query = Query::parse($parsed_url['query']);
    $this->assertArrayHasKey('code', $parsed_query);
    return $parsed_query['code'];
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
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
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
