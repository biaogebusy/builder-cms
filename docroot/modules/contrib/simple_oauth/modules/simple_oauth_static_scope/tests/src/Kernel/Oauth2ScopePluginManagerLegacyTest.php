<?php

namespace Drupal\Tests\simple_oauth_static_scope\Kernel;

use Drupal\Core\Session\AccountInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\simple_oauth\Oauth2ScopeInterface;

/**
 * Test OAuth2 scopes plugin manager.
 *
 * @group simple_oauth_static_scope
 * @group legacy
 */
class Oauth2ScopePluginManagerLegacyTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'serialization',
    'simple_oauth',
    'simple_oauth_static_scope',
    'simple_oauth_static_scope_legacy_test',
    'system',
    'user',
  ];

  /**
   * The OAuth2 scope manager.
   *
   * @var \Drupal\simple_oauth_static_scope\Plugin\Oauth2ScopeManagerInterface
   */
  protected $oauth2ScopeManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installConfig(['user']);

    $this->oauth2ScopeManager = $this->container->get('plugin.manager.oauth2_scope');
  }

  /**
   * Tests scope plugins using a deprecated definition structure.
   *
   * @legacy
   */
  public function testDiscoveryDeprecated(): void {
    $expected_scopes = [
      'deprecated:permission' => [
        'id' => 'deprecated:permission',
        'description' => 'Test deprecated:permission description',
        'umbrella' => FALSE,
        'granularity' => Oauth2ScopeInterface::GRANULARITY_PERMISSION,
        'permission' => 'debug simple_oauth tokens',
        'class' => 'Drupal\\simple_oauth\\Plugin\\Oauth2Scope',
      ],
      'deprecated:role' => [
        'id' => 'deprecated:role',
        'description' => 'Test deprecated:role description',
        'umbrella' => FALSE,
        'granularity' => Oauth2ScopeInterface::GRANULARITY_ROLE,
        'role' => AccountInterface::AUTHENTICATED_ROLE,
        'class' => 'Drupal\\simple_oauth\\Plugin\\Oauth2Scope',
      ],
    ];

    $this->expectDeprecation('Using the "granularity" property for OAuth2 scope plugin "deprecated:permission" is deprecated in version simple_oauth:6.0.0. Use the "granularity_id" property instead. See https://www.drupal.org/node/3492888');
    $this->expectDeprecation('Using the top-level "permission" property for OAuth2 scope plugin "deprecated:permission" is deprecated in version simple_oauth:6.0.0. Place a "permission" sub-property inside of a new top-level "granularity_configuration" property instead. See https://www.drupal.org/node/3492888');
    $this->expectDeprecation('Using the "granularity" property for OAuth2 scope plugin "deprecated:role" is deprecated in version simple_oauth:6.0.0. Use the "granularity_id" property instead. See https://www.drupal.org/node/3492888');
    $this->expectDeprecation('Using the top-level "role" property for OAuth2 scope plugin "deprecated:role" is deprecated in version simple_oauth:6.0.0. Place a "role" sub-property inside of a new top-level "granularity_configuration" property instead. See https://www.drupal.org/node/3492888');
    $scopes = $this->oauth2ScopeManager->getDefinitions();
    // Test expected array keys.
    $this->assertEquals(array_keys($expected_scopes), array_keys($scopes));

    // Test expected order.
    $this->assertSame(array_keys($expected_scopes), array_keys($scopes));
  }

}
