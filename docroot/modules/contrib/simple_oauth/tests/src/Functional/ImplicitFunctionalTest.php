<?php

namespace Drupal\Tests\simple_oauth\Functional;

use Drupal\Core\Url;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;

/**
 * The implicit tests.
 *
 * @group simple_oauth
 */
class ImplicitFunctionalTest extends TokenBearerFunctionalTestBase {

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
    $this->client->save();
    $this->authorizeUrl = Url::fromRoute('oauth2_token.authorize');
    $this->grantPermissions(Role::load(RoleInterface::AUTHENTICATED_ID), [
      'grant simple_oauth codes',
    ]);
  }

  /**
   * Test the valid Implicit grant.
   */
  public function testImplicitGrant(): void {
    $valid_params = [
      'response_type' => 'token',
      'client_id' => $this->client->getClientId(),
      'client_secret' => $this->clientSecret,
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
    $assert_session = $this->assertSession();
    $assert_session->statusCodeEquals(500);
    $this
      ->config('simple_oauth.settings')
      ->set('use_implicit', TRUE)
      ->save();
    $this->drupalGet($this->authorizeUrl->toString(), [
      'query' => $valid_params,
    ]);
    $assert_session = $this->assertSession();
    $assert_session->statusCodeEquals(200);
    $assert_session->titleEquals('Grant Access to Client | Drupal');
    $assert_session->buttonExists('Grant');
    $assert_session->responseContains('Permissions');
    $this->drupalGet($this->authorizeUrl, [
      'query' => $valid_params,
    ]);

    // 3. Grant access by submitting the form and get the token back.
    $this->submitForm([], 'Grant');
    $assert_session = $this->assertSession();
    $assert_session->statusCodeEquals(200);
    $assert_session->addressMatches('/\/oauth\/test#access_token=.*&token_type=Bearer&expires_in=\d*/');
  }

  /**
   * Test the valid Implicit grant if the client is non 3rd party.
   */
  public function testValidClientImplicitGrant(): void {
    $this->client->set('third_party', FALSE);
    $this->client->save();
    $valid_params = [
      'response_type' => 'token',
      'client_id' => $this->client->getClientId(),
      'client_secret' => $this->clientSecret,
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
    $assert_session = $this->assertSession();
    $assert_session->responseContains('Fatal error. Unable to get the authorization server.');
    $this
      ->config('simple_oauth.settings')
      ->set('use_implicit', TRUE)
      ->save();
    $this->drupalGet($this->authorizeUrl->toString(), [
      'query' => $valid_params,
    ]);
    $assert_session = $this->assertSession();
    $assert_session->statusCodeEquals(200);
    $assert_session->addressMatches('/\/oauth\/test#access_token=.*&token_type=Bearer&expires_in=\d*/');
  }

}
