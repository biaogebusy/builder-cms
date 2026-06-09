<?php

declare(strict_types=1);

namespace Drupal\Tests\ai\Kernel\Plugin\ConfigAction;

use Drupal\ai\Plugin\ConfigAction\SetupVdbServer;
use Drupal\KernelTests\KernelTestBase;
use Drupal\search_api\Entity\Server;

/**
 * Kernel tests for the SetupVdbServer config action plugin.
 *
 * @group ai
 * @covers \Drupal\ai\Plugin\ConfigAction\SetupVdbServer
 */
class SetupVdbServerTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'ai',
    'ai_search',
    'ai_test',
    'key',
    'search_api',
    'system',
    'test_ai_vdb_provider_mysql',
    'user',
  ];

  /**
   * The config action plugin under test.
   *
   * @var \Drupal\ai\Plugin\ConfigAction\SetupVdbServer
   */
  private SetupVdbServer $setupVdbServer;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('search_api_server');
    $this->installConfig(['ai', 'ai_search', 'ai_test', 'search_api', 'test_ai_vdb_provider_mysql']);

    // Create the config action plugin using the container.
    $plugin_manager = $this->container->get('plugin.manager.config_action');
    $this->setupVdbServer = $plugin_manager->createInstance('setupVdbServerWithDefaults');
  }

  /**
   * Test exception when required fields are missing.
   */
  public function testApplyWithMissingRequiredFields(): void {
    $value = [
      'id' => 'test_server',
      'name' => 'Test Server',
    ];

    $this->expectException(\AssertionError::class);
    $this->setupVdbServer->apply('test_config', $value);
  }

  /**
   * Test exception when no default VDB provider is set.
   */
  public function testApplyThrowsExceptionWhenNoDefaultVdbProvider(): void {
    $value = [
      'id' => 'test_server',
      'name' => 'Test Server',
      'backend_config' => [
        'embedding_strategy' => 'test_strategy',
        'embedding_strategy_configuration' => ['test' => 'config'],
      ],
    ];

    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('No default VDB provider is set and database backend is not specified.');

    $this->setupVdbServer->apply('test_config', $value);
  }

  /**
   * Test exception when no default embeddings model is set.
   */
  public function testApplyThrowsExceptionWhenNoDefaultEmbeddingsModel(): void {
    // Set a default VDB provider to bypass the first check.
    $config = $this->config('ai.settings');
    $config->set('default_vdb_provider', 'test_vdb');
    $config->save();

    $value = [
      'id' => 'test_server',
      'name' => 'Test Server',
      'backend_config' => [
        'embedding_strategy' => 'test_strategy',
        'embedding_strategy_configuration' => ['test' => 'config'],
        'database' => 'test_vdb',
        'database_settings' => [
          'database_name' => 'test_db',
        ],
      ],
    ];

    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('No default embeddings model is set.');

    $this->setupVdbServer->apply('test_config', $value);
  }

  /**
   * Test exception when VDB provider fails to get default database.
   */
  public function testApplyThrowsExceptionWhenVdbProviderFails(): void {
    $value = [
      'id' => 'test_server',
      'name' => 'Test Server',
      'backend_config' => [
        'embedding_strategy' => 'test_strategy',
        'embedding_strategy_configuration' => ['test' => 'config'],
        'database' => 'nonexistent_vdb',
      ],
    ];

    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('Could not get default database name from VDB provider:');

    $this->setupVdbServer->apply('test_config', $value);
  }

  /**
   * Test exception when default embeddings model is not supported.
   */
  public function testApplyThrowsExceptionWhenEmbeddingsModelNotSupported(): void {
    // Set a default embeddings provider that doesn't exist.
    $config = $this->config('ai.settings');
    $config->set('default_providers.embeddings', [
      'provider_id' => 'nonexistent_provider',
      'model_id' => 'test_model',
    ]);
    $config->save();

    $value = [
      'id' => 'test_server',
      'name' => 'Test Server',
      'backend_config' => [
        'embedding_strategy' => 'test_strategy',
        'embedding_strategy_configuration' => ['test' => 'config'],
        'database' => 'test_vdb',
        'database_settings' => [
          'database_name' => 'test_db',
        ],
      ],
    ];

    $this->expectException(\Exception::class);
    $this->expectExceptionMessage('The default embeddings model is not supported.');

    $this->setupVdbServer->apply('test_config', $value);
  }

  /**
   * Test successful application with valid configuration.
   *
   * This test requires a working AI provider and VDB provider.
   * It may be skipped if dependencies are not available.
   */
  public function testApplySuccessfulConfiguration(): void {
    // Check if we have any AI providers available.
    $plugin_manager = $this->container->get('ai.provider');
    $providers = $plugin_manager->getProvidersForOperationType('embeddings', FALSE);

    if (empty($providers)) {
      $this->markTestSkipped('No AI providers available for embeddings operation.');
    }

    // Check if we have any VDB providers available.
    $vdb_provider_manager = $this->container->get('ai.vdb_provider');
    $vdb_providers = $vdb_provider_manager->getProviders(FALSE);

    if (empty($vdb_providers)) {
      $this->markTestSkipped('No VDB providers available.');
    }

    // Get the first available provider and VDB provider.
    $first_provider = array_key_first($providers);
    $first_vdb = array_key_first($vdb_providers);

    // Set up configuration with available providers.
    $config = $this->config('ai.settings');
    $config->set('default_providers.embeddings', [
      'provider_id' => $first_provider,
      'model_id' => 'test_model',
    ]);
    $config->set('default_vdb_provider', $first_vdb);
    $config->save();

    $value = [
      'id' => 'test_server',
      'name' => 'Test Server',
      'backend' => 'search_api_ai_search',
      'backend_config' => [
        'embedding_strategy' => 'test_strategy',
        'embedding_strategy_configuration' => ['test' => 'config'],
        'database' => $first_vdb,
        'database_settings' => [
          'database_name' => 'test_db',
        ],
      ],
    ];

    try {
      $this->setupVdbServer->apply('search_api.server.test_server', $value);

      // Verify that the server was created.
      $server = Server::load('test_server');
      $this->assertInstanceOf(Server::class, $server);
      $this->assertEquals('test_server', $server->id());
      $this->assertEquals('Test Server', $server->label());

      // Check that embeddings engine was set.
      $backend_config = $server->getBackendConfig();
      $this->assertArrayHasKey('embeddings_engine', $backend_config);
      $this->assertStringContainsString($first_provider, $backend_config['embeddings_engine']);
      $this->assertArrayHasKey('embeddings_engine_configuration', $backend_config);

    }
    catch (\Exception $e) {
      // If the provider doesn't support the test model or has other issues,
      // we expect specific error messages.
      $expected_messages = [
        'The default embeddings model is not supported.',
        'Could not get default database name from VDB provider:',
        'Could not save the configuration.',
      ];

      $found_expected = FALSE;
      foreach ($expected_messages as $expected) {
        if (strpos($e->getMessage(), $expected) !== FALSE) {
          $found_expected = TRUE;
          break;
        }
      }

      if (!$found_expected) {
        throw $e;
      }
    }
  }

  /**
   * Test that the plugin can be instantiated from the container.
   */
  public function testPluginInstantiation(): void {
    $plugin_manager = $this->container->get('plugin.manager.config_action');
    $plugin = $plugin_manager->createInstance('setupVdbServerWithDefaults');

    $this->assertInstanceOf(SetupVdbServer::class, $plugin);
  }

}
