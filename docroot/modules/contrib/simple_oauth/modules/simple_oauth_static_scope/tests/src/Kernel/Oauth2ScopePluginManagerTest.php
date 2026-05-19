<?php

namespace Drupal\Tests\simple_oauth_static_scope\Kernel;

use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\KernelTests\KernelTestBase;
use Drupal\simple_oauth\Oauth2ScopeInterface;

/**
 * Test OAuth2 scopes plugin manager.
 *
 * @group simple_oauth_static_scope
 */
class Oauth2ScopePluginManagerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'serialization',
    'simple_oauth',
    'simple_oauth_static_scope',
    'simple_oauth_static_scope_test',
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
   * Tests that scope plugins are discovered correctly.
   */
  public function testDiscovery(): void {
    $expected_scopes = [
      'static_scope' => [
        'id' => 'static_scope',
        'description' => 'Test static:scope description',
        'umbrella' => TRUE,
        'grant_types' => [
          'authorization_code' => [
            'status' => TRUE,
            'description' => 'Test authorization_code description',
          ],
        ],
        'class' => 'Drupal\\simple_oauth\\Plugin\\Oauth2Scope',
      ],
      'static_scope:child' => [
        'id' => 'static_scope:child',
        'description' => 'Test static:scope:child description',
        'umbrella' => FALSE,
        'grant_types' => [
          'authorization_code' => [
            'status' => TRUE,
            'description' => 'Test authorization_code description',
          ],
          'client_credentials' => [
            'status' => TRUE,
            'description' => 'Test client_credentials description',
          ],
        ],
        'parent' => 'static_scope',
        'granularity_id' => Oauth2ScopeInterface::GRANULARITY_PERMISSION,
        'granularity_configuration' => [
          'permission' => 'debug simple_oauth tokens',
        ],
        'class' => 'Drupal\\simple_oauth\\Plugin\\Oauth2Scope',
      ],
      'static_scope:child:child' => [
        'id' => 'static_scope:child:child',
        'description' => 'Test static:scope:child:child description',
        'umbrella' => FALSE,
        'grant_types' => [
          'authorization_code' => [
            'status' => TRUE,
            'description' => 'Test authorization_code description',
          ],
          'client_credentials' => [
            'status' => TRUE,
            'description' => 'Test client_credentials description',
          ],
        ],
        'parent' => 'static_scope:child',
        'granularity_id' => Oauth2ScopeInterface::GRANULARITY_PERMISSION,
        'granularity_configuration' => [
          'permission' => 'access content',
        ],
        'class' => 'Drupal\\simple_oauth\\Plugin\\Oauth2Scope',
      ],
      'static_scope:role' => [
        'id' => 'static_scope:role',
        'description' => 'Test static_scope:role description',
        'umbrella' => FALSE,
        'grant_types' => [
          'authorization_code' => [
            'status' => TRUE,
            'description' => 'Test authorization_code description',
          ],
        ],
        'granularity_id' => Oauth2ScopeInterface::GRANULARITY_ROLE,
        'granularity_configuration' => [
          'role' => AccountInterface::AUTHENTICATED_ROLE,
        ],
        'class' => 'Drupal\\simple_oauth\\Plugin\\Oauth2Scope',
      ],
      'static_scope:role:child' => [
        'id' => 'static_scope:role:child',
        'description' => 'Test static_scope:role:child description',
        'umbrella' => FALSE,
        'grant_types' => [
          'authorization_code' => [
            'status' => TRUE,
            'description' => 'Test authorization_code description',
          ],
        ],
        'granularity_id' => Oauth2ScopeInterface::GRANULARITY_PERMISSION,
        'granularity_configuration' => [
          'permission' => 'debug simple_oauth tokens',
        ],
        'class' => 'Drupal\\simple_oauth\\Plugin\\Oauth2Scope',
      ],
    ];

    $scopes = $this->oauth2ScopeManager->getDefinitions();
    // Test expected array keys.
    $this->assertEquals(array_keys($expected_scopes), array_keys($scopes));

    // Test expected order.
    $this->assertSame(array_keys($expected_scopes), array_keys($scopes));
  }

  /**
   * Tests that the scope plugin is translatable.
   */
  public function testTranslation(): void {
    $scope = $this->oauth2ScopeManager->getDefinition('static_scope');
    $expected_instance = TranslatableMarkup::class;
    $this->assertInstanceOf($expected_instance, $scope['description']);
    foreach ($scope['grant_types'] as $grant_type) {
      $this->assertInstanceOf($expected_instance, $grant_type['description']);
    }
  }

}
