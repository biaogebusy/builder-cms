<?php

namespace Drupal\Tests\simple_oauth_static_scope\Unit;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Language\Language;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\simple_oauth\Oauth2ScopeInterface;
use Drupal\Tests\UnitTestCase;
use Drupal\simple_oauth_static_scope\Plugin\Oauth2ScopeManager;
use Drupal\simple_oauth_static_scope\Plugin\Oauth2ScopeManagerInterface;

/**
 * @coversDefaultClass \Drupal\simple_oauth_static_scope\Plugin\Oauth2ScopeManager
 * @group simple_oauth_static_scope
 */
class Oauth2ScopeManagerTest extends UnitTestCase {

  /**
   * The tested scope plugin manager.
   *
   * @var \Drupal\simple_oauth_static_scope\Plugin\Oauth2ScopeManagerInterface
   */
  protected Oauth2ScopeManagerInterface $scopeManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->scopeManager = $this
      ->getMockBuilder(Oauth2ScopeManager::class)
      ->disableOriginalConstructor()
      ->onlyMethods([])
      ->getMock();

    $plugin_discovery = $this->createMock('Drupal\Component\Plugin\Discovery\DiscoveryInterface');
    $factory = $this->createMock('Drupal\Component\Plugin\Factory\FactoryInterface');
    $cache_backend = $this->createMock('Drupal\Core\Cache\CacheBackendInterface');
    $module_handler = $this->createMock('Drupal\Core\Extension\ModuleHandlerInterface');
    $granularity_manager = $this->createMock('Drupal\simple_oauth\Plugin\ScopeGranularityManager');
    $grant_manager = $this->createMock('Drupal\simple_oauth\Plugin\Oauth2GrantManagerInterface');
    $grant_manager->expects($this->any())
      ->method('getDefinitions')
      ->willReturn(array_flip([
        'authorization_code',
        'client_credentials',
      ]));

    $property = new \ReflectionProperty(Oauth2ScopeManager::class, 'discovery');
    $property->setAccessible(TRUE);
    $property->setValue($this->scopeManager, $plugin_discovery);

    $property = new \ReflectionProperty(Oauth2ScopeManager::class, 'factory');
    $property->setAccessible(TRUE);
    $property->setValue($this->scopeManager, $factory);

    $property = new \ReflectionProperty(Oauth2ScopeManager::class, 'moduleHandler');
    $property->setAccessible(TRUE);
    $property->setValue($this->scopeManager, $module_handler);

    $property = new \ReflectionProperty(Oauth2ScopeManager::class, 'granularityManager');
    $property->setAccessible(TRUE);
    $property->setValue($this->scopeManager, $granularity_manager);

    $property = new \ReflectionProperty(Oauth2ScopeManager::class, 'grantManager');
    $property->setAccessible(TRUE);
    $property->setValue($this->scopeManager, $grant_manager);

    $language_manager = $this->createMock(LanguageManagerInterface::class);
    $language_manager->expects($this->any())
      ->method('getCurrentLanguage')
      ->willReturn(new Language(['id' => 'en']));

    $method = new \ReflectionMethod(Oauth2ScopeManager::class, 'alterInfo');
    $method->setAccessible(TRUE);
    $method->invoke($this->scopeManager, 'oauth2_scope_info');

    $this->scopeManager->setCacheBackend($cache_backend, 'oauth2_scope:en');

    $container = new ContainerBuilder();
    $container->set('string_translation', $this->createMock(TranslationInterface::class));
    \Drupal::setContainer($container);
  }

  /**
   * Tests processDefinition().
   *
   * @covers ::processDefinition
   * @dataProvider pluginExceptionsData
   */
  public function testProcessDefinition(array $definition, string $plugin_id): void {
    $this->expectException(PluginException::class);
    $this->scopeManager->processDefinition($definition, $plugin_id);
  }

  /**
   * Provides data for testProcessDefinition.
   *
   * @return array
   *   The plugin definitions.
   */
  public static function pluginExceptionsData(): array {
    $default_grant_types = [
      'authorization_code' => [
        'description' => 'Test authorization_code description',
      ],
    ];
    return [
      'plugin_id_with_spaces' => [
        'definition' => [
          'description' => 'plugin id with spaces',
          'grant_types' => $default_grant_types,
          'umbrella' => TRUE,
        ],
        'plugin_id' => 'plugin id with spaces',
      ],
      'plugin_id_with_semicolons' => [
        'definition' => [
          'description' => 'plugin id with semicolons',
          'grant_types' => $default_grant_types,
          'umbrella' => TRUE,
        ],
        'plugin_id' => 'plugin;id;with;semicolons',
      ],
      'plugin_id_with_dashes' => [
        'definition' => [
          'description' => 'plugin id with dashes',
          'grant_types' => $default_grant_types,
          'umbrella' => TRUE,
        ],
        'plugin_id' => 'plugin-id-with-dashes',
      ],
      'required_description' => [
        'definition' => [
          'grant_types' => $default_grant_types,
          'umbrella' => TRUE,
        ],
        'plugin_id' => 'required:description',
      ],
      'required_grant_types' => [
        'definition' => [
          'description' => 'required grant types',
          'umbrella' => TRUE,
        ],
        'plugin_id' => 'required:grant:types',
      ],
      'undefined_grant_type' => [
        'definition' => [
          'description' => 'undefined grant type',
          'grant_types' => [
            'undefined_grant_type',
          ],
          'umbrella' => TRUE,
        ],
        'plugin_id' => 'undefined:grant_type',
      ],
      'required_umbrella' => [
        'definition' => [
          'description' => 'required umbrella',
          'grant_types' => $default_grant_types,
        ],
        'plugin_id' => 'required:umbrella',
      ],
      'required_permission' => [
        'definition' => [
          'description' => 'required permission',
          'grant_types' => $default_grant_types,
          'umbrella' => FALSE,
        ],
        'plugin_id' => 'required:permission',
      ],
      'undefined_permission' => [
        'definition' => [
          'description' => 'undefined permission',
          'grant_types' => $default_grant_types,
          'umbrella' => FALSE,
          'permission' => 'undefined permission',
        ],
        'plugin_id' => 'required:permission',
      ],
      'self_parent' => [
        'definition' => [
          'description' => 'self parent',
          'grant_types' => $default_grant_types,
          'umbrella' => FALSE,
          'permission' => 'view oauth2 scopes',
          'parent' => 'self:parent',
        ],
        'plugin_id' => 'self:parent',
      ],
    ];
  }

  /**
   * Tests validateParent().
   *
   * @covers ::validateParent
   */
  public function testValidateParent(): void {
    $default_definition = [
      'grant_types' => [
        'authorization_code' => [
          'description' => 'Test authorization_code description',
        ],
      ],
      'umbrella' => FALSE,
      'granularity_id' => Oauth2ScopeInterface::GRANULARITY_PERMISSION,
      'granularity_configuration' => [
        'permission' => 'view oauth2 scopes',
      ],
    ];
    $definitions = [
      'recursive_scope_a' => [
        'label' => 'recursive:scope:a',
        'parent' => 'recursive_scope_b',
      ] + $default_definition,
      'recursive_scope_b' => [
        'label' => 'recursive:scope:b',
        'parent' => 'recursive_scope_a',
      ] + $default_definition,
    ];
    $method = new \ReflectionMethod(Oauth2ScopeManager::class, 'validateParent');
    $method->setAccessible(TRUE);
    $this->expectException(PluginException::class);
    $method->invoke($this->scopeManager, $definitions);
  }

}
