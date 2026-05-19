<?php

namespace Drupal\Tests\simple_oauth\Kernel;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\simple_oauth\Entity\Oauth2Scope;
use Drupal\simple_oauth\Oauth2ScopeInterface;
use Drupal\simple_oauth\Plugin\ScopeGranularity\Permission;
use Drupal\simple_oauth\Plugin\ScopeGranularity\Role;

/**
 * Tests for OAuth2 scope entity.
 *
 * @group simple_oauth
 */
class Oauth2ScopeEntityTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'serialization',
    'system',
    'simple_oauth',
    'user',
  ];

  /**
   * The scope granularity manager.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  protected PluginManagerInterface $granularityManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('oauth2_scope');

    $this->granularityManager = $this->container->get('plugin.manager.scope_granularity');
  }

  /**
   * Tests create operations for OAuth2 scope entity with permission.
   */
  public function testCreateScopePermission(): void {
    $values = [
      'name' => 'test:test',
      'description' => $this->getRandomGenerator()->sentences(5),
      'grant_types' => [
        'authorization_code' => [
          'status' => TRUE,
          'description' => $this->getRandomGenerator()->sentences(5),
        ],
      ],
      'umbrella' => FALSE,
      'parent' => 'test_parent',
      'granularity_id' => Oauth2ScopeInterface::GRANULARITY_PERMISSION,
      'granularity_configuration' => [
        'permission' => 'view own simple_oauth entities',
      ],
    ];
    /** @var \Drupal\simple_oauth\Entity\Oauth2ScopeEntityInterface $scope */
    $scope = Oauth2Scope::create($values);
    $scope->save();

    $this->assertEquals(Oauth2Scope::scopeToMachineName($values['name']), $scope->id());
    $this->assertEquals($values['name'], $scope->getName());
    $this->assertEquals($values['description'], $scope->getDescription());
    $this->assertEquals($values['grant_types'], $scope->getGrantTypes());
    $this->assertEquals($values['grant_types']['authorization_code']['description'], $scope->getGrantTypeDescription('authorization_code'));
    $this->assertEquals($values['umbrella'], $scope->isUmbrella());
    $this->assertEquals($values['parent'], $scope->getParent());
    $this->assertInstanceOf(Permission::class, $scope->getGranularity());
    $this->assertEquals($values['granularity_id'], $scope->getGranularity()->getPluginId());
    $this->assertEquals($values['granularity_configuration'], $scope->getGranularity()->getConfiguration());
  }

  /**
   * Tests create operations for OAuth2 scope entity with role.
   */
  public function testCreateScopeRole(): void {
    $values = [
      'name' => 'test:test',
      'description' => $this->getRandomGenerator()->sentences(5),
      'grant_types' => [
        'client_credentials' => [
          'status' => TRUE,
          'description' => $this->getRandomGenerator()->sentences(5),
        ],
      ],
      'umbrella' => FALSE,
      'parent' => 'test_parent',
      'granularity_id' => Oauth2ScopeInterface::GRANULARITY_ROLE,
      'granularity_configuration' => [
        'role' => AccountInterface::AUTHENTICATED_ROLE,
      ],
    ];
    /** @var \Drupal\simple_oauth\Entity\Oauth2ScopeEntityInterface $scope */
    $scope = Oauth2Scope::create($values);
    $scope->save();

    $this->assertEquals(Oauth2Scope::scopeToMachineName($values['name']), $scope->id());
    $this->assertEquals($values['name'], $scope->getName());
    $this->assertEquals($values['description'], $scope->getDescription());
    $this->assertEquals($values['grant_types'], $scope->getGrantTypes());
    $this->assertEquals($values['grant_types']['client_credentials']['description'], $scope->getGrantTypeDescription('client_credentials'));
    $this->assertEquals($values['umbrella'], $scope->isUmbrella());
    $this->assertEquals($values['parent'], $scope->getParent());
    $this->assertInstanceOf(Role::class, $scope->getGranularity());
    $this->assertEquals($values['granularity_id'], $scope->getGranularity()->getPluginId());
    $this->assertEquals($values['granularity_configuration'], $scope->getGranularity()->getConfiguration());

  }

}
