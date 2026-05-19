<?php

namespace Drupal\Tests\simple_oauth\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\simple_oauth\Entity\Oauth2Scope;
use Drupal\simple_oauth\Oauth2ScopeProviderInterface;

/**
 * Tests the scope granularity functionality.
 *
 * @group simple_oauth
 */
class ScopeGranularityTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'consumers',
    'serialization',
    'simple_oauth',
    'simple_oauth_test',
    'user',
  ];

  /**
   * The OAuth2 scope provider used in this test.
   *
   * @var \Drupal\simple_oauth\Oauth2ScopeProviderInterface
   */
  protected Oauth2ScopeProviderInterface $scopeProvider;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig(['simple_oauth']);

    $scopeProvider = $this->container->get('simple_oauth.oauth2_scope.provider');
    assert($scopeProvider instanceof Oauth2ScopeProviderInterface);
    $this->scopeProvider = $scopeProvider;
  }

  /**
   * Tests permission checking of scope granularities.
   *
   * For clarity, a list of string lengths:
   * access content: 14
   * administer nodes: 16
   * bypass node access: 18
   */
  public function testScopeHasPermission(): void {
    // Test normal scopes.
    $min17 = Oauth2Scope::create([
      'id' => 'min_17',
      'name' => 'min_17',
      'description' => 'Test scope that grants access to permissions longer than 17 characters',
      'granularity_id' => 'test',
      'granularity_configuration' => [
        'min_length' => 17,
      ],
    ]);
    $this->assertFalse($this->scopeProvider->scopeHasPermission('access content', $min17));
    $this->assertFalse($this->scopeProvider->scopeHasPermission('administer nodes', $min17));
    $this->assertTrue($this->scopeProvider->scopeHasPermission('bypass node access', $min17));

    $between10and15 = Oauth2Scope::create([
      'id' => 'between_10_and_15',
      'name' => 'between_10_and_15',
      'description' => 'Test scope that grants access to permissions between 10 and 15 characters',
      'granularity_id' => 'test',
      'granularity_configuration' => [
        'min_length' => 10,
        'max_length' => 15,
      ],
    ]);
    $this->assertTrue($this->scopeProvider->scopeHasPermission('access content', $between10and15));
    $this->assertFalse($this->scopeProvider->scopeHasPermission('administer nodes', $between10and15));
    $this->assertFalse($this->scopeProvider->scopeHasPermission('bypass node access', $between10and15));

    // Test an umbrella scope.
    $not16 = Oauth2Scope::create([
      'id' => 'not_16',
      'name' => 'not_16',
      'description' => 'Test scope that grants access to permissions longer than 10 characters except if they are 16 characters long',
      'umbrella' => TRUE,
    ]);
    $not16->save();
    $min17->set('parent', 'not_16')->save();
    $between10and15->set('parent', 'not_16')->save();
    $this->assertTrue($this->scopeProvider->scopeHasPermission('access content', $not16));
    $this->assertFalse($this->scopeProvider->scopeHasPermission('administer nodes', $not16));
    $this->assertTrue($this->scopeProvider->scopeHasPermission('bypass node access', $not16));

    // Test a nested scope as well as mixing scopes of different granularities.
    $min10 = Oauth2Scope::create([
      'id' => 'min_10',
      'name' => 'min_10',
      'description' => 'Test scope that grants access to permissions longer than 10 characters',
      'umbrella' => TRUE,
    ]);
    $min10->save();
    $permission = Oauth2Scope::create([
      'id' => 'permission',
      'name' => 'permission',
      'description' => 'Test scope that grants access to the "administer nodes" permission',
      'parent' => 'min_10',
      'granularity_id' => 'permission',
      'granularity_configuration' => [
        'permission' => 'administer nodes',
      ],
    ]);
    $permission->save();
    $not16->set('parent', 'min_10')->save();
    $this->assertTrue($this->scopeProvider->scopeHasPermission('access content', $min10));
    $this->assertTrue($this->scopeProvider->scopeHasPermission('administer nodes', $min10));
    $this->assertTrue($this->scopeProvider->scopeHasPermission('bypass node access', $min10));
  }

}
