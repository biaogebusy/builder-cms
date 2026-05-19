<?php

namespace Drupal\Tests\simple_oauth\Kernel;

use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\consumers\Entity\Consumer;
use Drupal\simple_oauth\Entity\Oauth2Scope;
use Drupal\simple_oauth\Oauth2ScopeInterface;

/**
 * Tests for consumer entity.
 *
 * @group simple_oauth
 */
class ConsumerEntityTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'consumers',
    'field',
    'file',
    'image',
    'options',
    'serialization',
    'system',
    'simple_oauth',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('file');
    $this->installEntitySchema('consumer');
    $this->installEntitySchema('oauth2_scope');
    $this->installEntitySchema('oauth2_token');
    $this->installConfig(['field']);
    $this->installConfig(['user']);
    $this->installConfig(['simple_oauth']);
  }

  /**
   * Tests create operation for consumer entity.
   */
  public function testCreate(): void {
    $scope = Oauth2Scope::create([
      'name' => 'test:test',
      'description' => $this->getRandomGenerator()->sentences(5),
      'grant_types' => [
        'authorization_code' => [
          'status' => TRUE,
        ],
        'client_credentials' => [
          'status' => TRUE,
        ],
      ],
      'umbrella' => FALSE,
      'granularity_id' => Oauth2ScopeInterface::GRANULARITY_PERMISSION,
      'granularity_configuration' => [
        'permission' => 'view own simple_oauth entities',
      ],
    ]);
    $scope->save();
    $values = [
      'client_id' => 'test_client',
      'label' => 'test',
      'grant_types' => ['authorization_code', 'client_credentials'],
      'scopes' => [$scope->id()],
      'confidential' => TRUE,
      'pkce' => TRUE,
      'redirect' => [
        'mobile://test.com',
        'http://localhost',
      ],
      'access_token_expiration' => 600,
      'refresh_token_expiration' => 2419200,
      'automatic_authorization' => TRUE,
      'remember_approval' => FALSE,
    ];
    $consumer = Consumer::create($values);
    $consumer->save();

    $this->assertEquals($values['client_id'], $consumer->getClientId());
    $this->assertEquals($values['label'], $consumer->label());
    foreach ($values['grant_types'] as $delta => $grant_type) {
      $this->assertEquals($grant_type, $consumer->get('grant_types')->get($delta)->value);
    }
    foreach ($values['scopes'] as $delta => $scope) {
      $this->assertEquals($scope, $consumer->get('scopes')->get($delta)->scope_id);
      $this->assertInstanceOf(Oauth2ScopeInterface::class, $consumer->get('scopes')->get($delta)->getScope());
    }
    $this->assertEquals($values['confidential'], $consumer->get('confidential')->value);
    $this->assertEquals($values['pkce'], $consumer->get('pkce')->value);
    foreach ($values['redirect'] as $delta => $redirect) {
      $this->assertEquals($redirect, $consumer->get('redirect')->get($delta)->value);
    }
    $this->assertEquals($values['access_token_expiration'], $consumer->get('access_token_expiration')->value);
    $this->assertEquals($values['refresh_token_expiration'], $consumer->get('refresh_token_expiration')->value);
    $this->assertEquals($values['automatic_authorization'], $consumer->get('automatic_authorization')->value);
    $this->assertEquals($values['remember_approval'], $consumer->get('remember_approval')->value);
  }

  /**
   * Test default values for the enriched BaseFields on the consumer entity.
   */
  public function testDefaultValues(): void {
    $consumer = Consumer::create([
      'client_id' => 'test_client',
      'label' => 'test client',
      'grant_types' => ['authorization_code'],
      'redirect' => [
        'http://test',
      ],
    ]);
    $consumer->save();

    $this->assertEquals(300, $consumer->get('access_token_expiration')->value);
    $this->assertEquals(1209600, $consumer->get('refresh_token_expiration')->value);
    $this->assertEquals(FALSE, (bool) $consumer->get('automatic_authorization')->value);
    $this->assertEquals(TRUE, (bool) $consumer->get('remember_approval')->value);
    $this->assertEquals(TRUE, (bool) $consumer->get('confidential')->value);
    $this->assertEquals(FALSE, (bool) $consumer->get('pkce')->value);
  }

  /**
   * Test scope filtering by grant type on consumer fields.
   */
  public function testScopeFilteringByGrantType(): void {
    // Create scopes with different grant type configurations.
    $authCodeScope = Oauth2Scope::create([
      'name' => 'auth_code_only',
      'grant_types' => [
        'authorization_code' => ['status' => TRUE],
        'client_credentials' => ['status' => FALSE],
      ],
      'granularity_id' => Oauth2ScopeInterface::GRANULARITY_PERMISSION,
      'granularity_configuration' => ['permission' => 'access content'],
    ]);
    $authCodeScope->save();

    $clientCredsScope = Oauth2Scope::create([
      'name' => 'client_creds_only',
      'grant_types' => [
        'authorization_code' => ['status' => FALSE],
        'client_credentials' => ['status' => TRUE],
      ],
      'granularity_id' => Oauth2ScopeInterface::GRANULARITY_PERMISSION,
      'granularity_configuration' => ['permission' => 'access content'],
    ]);
    $clientCredsScope->save();

    $bothScope = Oauth2Scope::create([
      'name' => 'both_grants',
      'grant_types' => [
        'authorization_code' => ['status' => TRUE],
        'client_credentials' => ['status' => TRUE],
      ],
      'granularity_id' => Oauth2ScopeInterface::GRANULARITY_PERMISSION,
      'granularity_configuration' => ['permission' => 'access content'],
    ]);
    $bothScope->save();

    // Create consumer with both grant types.
    $consumer = Consumer::create([
      'client_id' => 'test_scope_filter',
      'label' => 'Test Scope Filtering',
      'grant_types' => ['authorization_code', 'client_credentials'],
      'redirect' => ['http://localhost'],
    ]);
    $consumer->save();

    // Test authorization_code_scopes field filtering.
    $consumer->set('authorization_code_scopes', [$authCodeScope->id()]);
    $auth_field = $consumer->get('authorization_code_scopes')->first();
    $auth_options = $auth_field->getPossibleOptions();

    $this->assertArrayHasKey($authCodeScope->id(), $auth_options, 'authorization_code_scopes should include auth_code_only scope');
    $this->assertArrayHasKey($bothScope->id(), $auth_options, 'authorization_code_scopes should include both_grants scope');
    $this->assertArrayNotHasKey($clientCredsScope->id(), $auth_options, 'authorization_code_scopes should NOT include client_creds_only scope');

    // Test scopes field (client_credentials) filtering.
    $consumer->set('scopes', [$clientCredsScope->id()]);
    $creds_field = $consumer->get('scopes')->first();
    $creds_options = $creds_field->getPossibleOptions();

    $this->assertArrayHasKey($clientCredsScope->id(), $creds_options, 'scopes field should include client_creds_only scope');
    $this->assertArrayHasKey($bothScope->id(), $creds_options, 'scopes field should include both_grants scope');
    $this->assertArrayNotHasKey($authCodeScope->id(), $creds_options, 'scopes field should NOT include auth_code_only scope');
  }

  /**
   * Test that scope filtering ignores consumer grant types.
   *
   * Verifies that the oauth2_scope_reference field's filter_grant_type setting
   * controls scope visibility independently of the consumer's enabled grant
   * types. This ensures fields can show scopes for grant types not enabled
   * on the consumer, allowing flexible scope configuration.
   */
  public function testScopeFilteringIgnoresConsumerGrantTypes(): void {
    $authCodeScope = Oauth2Scope::create([
      'name' => 'scope_auth_code',
      'grant_types' => [
        'authorization_code' => ['status' => TRUE],
        'client_credentials' => ['status' => FALSE],
      ],
      'granularity_id' => Oauth2ScopeInterface::GRANULARITY_PERMISSION,
      'granularity_configuration' => ['permission' => 'access content'],
    ]);
    $authCodeScope->save();

    $clientCredsScope = Oauth2Scope::create([
      'name' => 'scope_client_creds',
      'grant_types' => [
        'authorization_code' => ['status' => FALSE],
        'client_credentials' => ['status' => TRUE],
      ],
      'granularity_id' => Oauth2ScopeInterface::GRANULARITY_PERMISSION,
      'granularity_configuration' => ['permission' => 'access content'],
    ]);
    $clientCredsScope->save();

    $bothGrantsScope = Oauth2Scope::create([
      'name' => 'scope_both_grants',
      'grant_types' => [
        'authorization_code' => ['status' => TRUE],
        'client_credentials' => ['status' => TRUE],
      ],
      'granularity_id' => Oauth2ScopeInterface::GRANULARITY_PERMISSION,
      'granularity_configuration' => ['permission' => 'access content'],
    ]);
    $bothGrantsScope->save();

    // Consumer with only authorization_code, but 'scopes' field filtered to
    // client_credentials.
    $authOnlyConsumer = Consumer::create([
      'client_id' => 'consumer_auth_only',
      'label' => 'Auth Code Only Consumer',
      'grant_types' => ['authorization_code'],
      'redirect' => ['http://localhost'],
    ]);
    $authOnlyConsumer->save();
    $authOnlyConsumer->set('scopes', [$clientCredsScope->id()]);

    $scopes_field = $authOnlyConsumer->get('scopes')->first();
    $available_scopes = $scopes_field->getPossibleOptions();

    $this->assertArrayHasKey($clientCredsScope->id(), $available_scopes);
    $this->assertArrayHasKey($bothGrantsScope->id(), $available_scopes);
    $this->assertArrayNotHasKey($authCodeScope->id(), $available_scopes);
    $this->assertCount(2, $available_scopes);

    // Consumer with only client_credentials, but 'authorization_code_scopes'
    // field filtered to authorization_code.
    $credsOnlyConsumer = Consumer::create([
      'client_id' => 'consumer_creds_only',
      'label' => 'Client Credentials Only Consumer',
      'grant_types' => ['client_credentials'],
      'redirect' => ['http://localhost'],
    ]);
    $credsOnlyConsumer->save();
    $credsOnlyConsumer->set('authorization_code_scopes', [$authCodeScope->id()]);

    $auth_code_field = $credsOnlyConsumer->get('authorization_code_scopes')->first();
    $available_auth_scopes = $auth_code_field->getPossibleOptions();

    $this->assertArrayHasKey($authCodeScope->id(), $available_auth_scopes);
    $this->assertArrayHasKey($bothGrantsScope->id(), $available_auth_scopes);
    $this->assertArrayNotHasKey($clientCredsScope->id(), $available_auth_scopes);
    $this->assertCount(2, $available_auth_scopes);

    // Consumer with no grant types should still get filtered scopes.
    $noGrantsConsumer = Consumer::create([
      'client_id' => 'consumer_no_grants',
      'label' => 'No Grants Consumer',
      'grant_types' => [],
      'redirect' => ['http://localhost'],
    ]);
    $noGrantsConsumer->save();
    $noGrantsConsumer->set('scopes', [$clientCredsScope->id()]);

    $no_grants_field = $noGrantsConsumer->get('scopes')->first();
    $no_grants_options = $no_grants_field->getPossibleOptions();

    $this->assertArrayHasKey($clientCredsScope->id(), $no_grants_options);
    $this->assertCount(2, $no_grants_options);
  }

  /**
   * Test that fields without filter_grant_type return all scopes.
   *
   * Verifies that when filter_grant_type is not set, all scopes are returned
   * regardless of their grant type configuration.
   */
  public function testNoFilterReturnsAllScopes(): void {
    $authCodeScope = Oauth2Scope::create([
      'name' => 'scope_for_auth_code',
      'grant_types' => [
        'authorization_code' => ['status' => TRUE],
        'client_credentials' => ['status' => FALSE],
      ],
      'granularity_id' => Oauth2ScopeInterface::GRANULARITY_PERMISSION,
      'granularity_configuration' => ['permission' => 'access content'],
    ]);
    $authCodeScope->save();

    $clientCredsScope = Oauth2Scope::create([
      'name' => 'scope_for_client_creds',
      'grant_types' => [
        'authorization_code' => ['status' => FALSE],
        'client_credentials' => ['status' => TRUE],
      ],
      'granularity_id' => Oauth2ScopeInterface::GRANULARITY_PERMISSION,
      'granularity_configuration' => ['permission' => 'access content'],
    ]);
    $clientCredsScope->save();

    $neitherScope = Oauth2Scope::create([
      'name' => 'scope_disabled',
      'grant_types' => [
        'authorization_code' => ['status' => FALSE],
        'client_credentials' => ['status' => FALSE],
      ],
      'granularity_id' => Oauth2ScopeInterface::GRANULARITY_PERMISSION,
      'granularity_configuration' => ['permission' => 'access content'],
    ]);
    $neitherScope->save();

    // Create a field without filter_grant_type setting.
    $field_storage = FieldStorageConfig::create([
      'field_name' => 'field_unfiltered_scopes',
      'entity_type' => 'consumer',
      'type' => 'oauth2_scope_reference',
      'cardinality' => FieldStorageDefinitionInterface::CARDINALITY_UNLIMITED,
    ]);
    $field_storage->save();

    $field = FieldConfig::create([
      'field_storage' => $field_storage,
      'bundle' => 'consumer',
      'label' => 'Unfiltered Scopes',
    ]);
    $field->save();

    $consumer = Consumer::create([
      'client_id' => 'test_unfiltered',
      'label' => 'Test Unfiltered',
      'grant_types' => ['authorization_code'],
      'redirect' => ['http://localhost'],
    ]);
    $consumer->save();
    $consumer->set('field_unfiltered_scopes', [$authCodeScope->id()]);

    $field_item = $consumer->get('field_unfiltered_scopes')->first();
    $available_scopes = $field_item->getPossibleOptions();

    // Should return ALL scopes regardless of grant type configuration.
    $this->assertArrayHasKey($authCodeScope->id(), $available_scopes);
    $this->assertArrayHasKey($clientCredsScope->id(), $available_scopes);
    $this->assertArrayHasKey($neitherScope->id(), $available_scopes);
    $this->assertCount(3, $available_scopes);
  }

}
