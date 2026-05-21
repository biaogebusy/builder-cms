<?php

namespace Drupal\Tests\simple_oauth\Functional;

use Drupal\consumers\Entity\Consumer;
use Drupal\Core\Routing\RouteBuilderInterface;
use Drupal\Core\State\StateInterface;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the OAuth2 client credentials token create form.
 *
 * @see \Drupal\simple_oauth\Form\Oauth2ClientCredentialsTokenCreateForm
 */
class Oauth2ClientCredentialsTokenCreateFormTest extends BrowserTestBase {

  use SimpleOauthTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'consumers',
    'simple_oauth',
    'simple_oauth_static_scope_test',
    'simple_oauth_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The state service used in the test.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected StateInterface $state;

  /**
   * The route builder used in the test.
   *
   * @var \Drupal\Core\Routing\RouteBuilderInterface
   */
  protected RouteBuilderInterface $routeBuilder;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->state = $this->container->get('state');
    $this->routeBuilder = $this->container->get('router.builder');

    $this->setUpKeys();

    $this->drupalPlaceBlock('local_actions_block');
    $this->drupalPlaceBlock('page_title_block');
  }

  /**
   * Tests the form.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function testForm(): void {
    $this->doTestFormAccess();
    $this->doTestFormValidation();
    $this->doTestTokenCreationSuccess();
    $this->doTestTokenCreationError();
  }

  /**
   * Tests access to the form.
   *
   * There are two requirements to be granted access to the form:
   * 1. The existence of confidential consumers with client credentials grants.
   * 2. The 'administer simple_oauth entities' permission.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function doTestFormAccess(): void {
    // First test without any appropriate consumers.
    Consumer::create([
      'label' => 'Consumer without client credentials grants',
      'client_id' => 'no_client_credentials',
      'scopes' => [
        'static_scope',
      ],
      'user_id' => 0,
    ])->save();
    Consumer::create([
      'label' => 'Consumer without scopes',
      'client_id' => 'no_scopes',
      'grant_types' => [
        'client_credentials',
      ],
      'user_id' => 0,
    ])->save();
    Consumer::create([
      'label' => 'Consumer without user',
      'client_id' => 'no_user',
      'grant_types' => [
        'client_credentials',
      ],
      'scopes' => [
        'static_scope',
      ],
    ])->save();
    Consumer::create([
      'label' => 'Non-confidential consumer',
      'client_id' => 'non_confidential',
      'grant_types' => [
        'client_credentials',
      ],
      'scopes' => [
        'static_scope',
      ],
      'user_id' => 0,
      'confidential' => FALSE,
    ])->save();

    $this->drupalLogin($this->createUser([
      'administer simple_oauth entities',
    ]));
    $this->drupalGet('/admin/config/people/simple_oauth/oauth2_token');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->linkNotExists('Create client credentials token');
    $this->drupalGet('/admin/config/people/simple_oauth/oauth2_token/create_client_credentials');
    $this->assertSession()->statusCodeEquals(403);

    // Now create appropriate consumers to verify the that the permission is
    // required.
    Consumer::create([
      'label' => 'First consumer with client credentials grants',
      'client_id' => 'client_credentials_1',
      'secret' => 'secret1',
      'grant_types' => [
        'client_credentials',
      ],
      'scopes' => [
        'static_scope',
      ],
      'user_id' => 0,
    ])->save();
    Consumer::create([
      'label' => 'Second consumer with client credentials grants',
      'client_id' => 'client_credentials_2',
      'secret' => 'secret2',
      'grant_types' => [
        'client_credentials',
      ],
      'scopes' => [
        'static_scope',
      ],
      'user_id' => 0,
    ])->save();

    $this->drupalLogin($this->createUser([
      'administer site configuration',
    ]));
    $this->drupalGet('/admin/config/people/simple_oauth/oauth2_token/create_client_credentials');
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalLogin($this->createUser([
      'administer simple_oauth entities',
    ]));
    $this->drupalGet('/admin/config/people/simple_oauth/oauth2_token');
    $this->assertSession()->statusCodeEquals(200);
    $this->getSession()->getPage()->clickLink('Create client credentials token');

    $this->assertSession()->addressEquals('/admin/config/people/simple_oauth/oauth2_token/create_client_credentials');
    $this->assertSession()->statusCodeEquals(200);

    $this->assertSession()->elementTextEquals('css', 'h1', 'Create client credentials token');
    $this->assertSession()->optionExists('Client', 'client_credentials_1');
    $this->assertSession()->optionExists('Client', 'client_credentials_2');
    $this->assertSession()->optionNotExists('Client', 'no_client_credentials');
    $this->assertSession()->optionNotExists('Client', 'no_scope');
    $this->assertSession()->optionNotExists('Client', 'no_user');
    $this->assertSession()->optionNotExists('Client', 'non_confidential');
  }

  /**
   * Tests the form validation.
   */
  protected function doTestFormValidation(): void {
    $this->submitForm([], 'Create token');
    $this->assertSession()->statusMessageContains('Client field is required', 'error');
    $this->assertSession()->statusMessageContains('Client secret field is required', 'error');

    $this->submitForm([
      'client' => 'client_credentials_1',
    ], 'Create token');
    $this->assertSession()->statusMessageContains('Client secret field is required', 'error');

    $this->submitForm([
      'client' => 'client_credentials_1',
      'client_secret' => 'incorrect secret',
    ], 'Create token');
    $this->assertSession()->statusMessageContains('Invalid secret for the First consumer with client credentials grants client', 'error');

    $this->submitForm([
      'client' => 'client_credentials_1',
      'client_secret' => 'secret2',
    ], 'Create token');
    $this->assertSession()->statusMessageContains('Invalid secret for the First consumer with client credentials grants client', 'error');
  }

  /**
   * Tests successful token creation.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  protected function doTestTokenCreationSuccess(): void {
    $this->submitForm([
      'client' => 'client_credentials_1',
      'client_secret' => 'secret1',
    ], 'Create token');
    $this->assertSession()->statusMessageContains('Successfully created token', 'status');

    $this->getSession()->getPage()->clickLink('Create client credentials token');
    $this->submitForm([
      'client' => 'client_credentials_2',
      'client_secret' => 'secret2',
    ], 'Create token');
    $this->assertSession()->statusMessageContains('Successfully created token', 'status');
  }

  /**
   * Tests that error states during token creation are handled.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   *
   * @see \Drupal\simple_oauth_test\Controller\TestOauth2Token::token()
   */
  protected function doTestTokenCreationError(): void {
    $this->getSession()->getPage()->clickLink('Create client credentials token');
    $this->state->set('simple_oauth_test_token_controller_override', 'exception');
    $this->routeBuilder->setRebuildNeeded();
    $this->submitForm([
      'client' => 'client_credentials_1',
      'client_secret' => 'secret1',
    ], 'Create token');
    $this->assertSession()->statusMessageContains('The token could not be created: Server error', 'error');

    $this->state->set('simple_oauth_test_token_controller_override', 'invalid_json');
    $this->routeBuilder->setRebuildNeeded();
    $this->submitForm([
      'client' => 'client_credentials_1',
      'client_secret' => 'secret1',
    ], 'Create token');
    $this->assertSession()->statusMessageContains('The token could not be created: Server error', 'error');

    $this->state->set('simple_oauth_test_token_controller_override', 'missing_access_token');
    $this->routeBuilder->setRebuildNeeded();
    $this->submitForm([
      'client' => 'client_credentials_1',
      'client_secret' => 'secret1',
    ], 'Create token');
    $this->assertSession()->statusMessageContains('The token could not be created: Server error', 'error');

    $this->state->set('simple_oauth_test_token_controller_override', 'invalid_jwt');
    $this->routeBuilder->setRebuildNeeded();
    $this->submitForm([
      'client' => 'client_credentials_1',
      'client_secret' => 'secret1',
    ], 'Create token');
    $this->assertSession()->statusMessageContains('The token could not be created: Server error', 'error');

    $this->state->set('simple_oauth_test_token_controller_override', 'invalid_base64');
    $this->routeBuilder->setRebuildNeeded();
    $this->submitForm([
      'client' => 'client_credentials_1',
      'client_secret' => 'secret1',
    ], 'Create token');
    $this->assertSession()->statusMessageContains('The token could not be created: Server error', 'error');

    $this->state->set('simple_oauth_test_token_controller_override', 'invalid_jwt_json');
    $this->routeBuilder->setRebuildNeeded();
    $this->submitForm([
      'client' => 'client_credentials_1',
      'client_secret' => 'secret1',
    ], 'Create token');
    $this->assertSession()->statusMessageContains('The token could not be created: Server error', 'error');

    $this->state->set('simple_oauth_test_token_controller_override', 'missing_claim');
    $this->routeBuilder->setRebuildNeeded();
    $this->submitForm([
      'client' => 'client_credentials_1',
      'client_secret' => 'secret1',
    ], 'Create token');
    $this->assertSession()->statusMessageContains('The token could not be created: Server error', 'error');

    $this->state->set('simple_oauth_test_token_controller_override', 'missing_jti');
    $this->routeBuilder->setRebuildNeeded();
    $this->submitForm([
      'client' => 'client_credentials_1',
      'client_secret' => 'secret1',
    ], 'Create token');
    $this->assertSession()->statusMessageContains('The token could not be created: Server error', 'error');
  }

}
