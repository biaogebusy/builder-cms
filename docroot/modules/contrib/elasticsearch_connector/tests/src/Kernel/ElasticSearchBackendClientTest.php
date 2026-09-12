<?php

declare(strict_types=1);

namespace Drupal\Tests\elasticsearch_connector\Kernel;

use Drupal\elasticsearch_connector\Plugin\search_api\backend\ElasticSearchBackend;
use Drupal\KernelTests\KernelTestBase;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\search_api\Utility\Utility;
use Drupal\Tests\search_api\Functional\ExampleContentTrait;

// cspell:ignore htdiw

/**
 * Test the module's Search API BackendClient.
 *
 * @group elasticsearch_connector
 */
class ElasticSearchBackendClientTest extends KernelTestBase {
  use ExampleContentTrait;
  use ElasticSearchTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'elasticsearch_connector',
    'elasticsearch_connector_test',
    'entity_test',
    'field',
    'filter',
    'search_api',
    'search_api_test_example_content',
    'system',
    'text',
    'user',
  ];

  /**
   * The machine name of the Search API Index we will use during the tests.
   *
   * @var string
   */
  protected string $indexId = 'test_elasticsearch_index';

  /**
   * The machine name of the Search API Server we will use during the tests.
   *
   * @var string
   */
  protected string $serverId = 'elasticsearch_server';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Setup: Install required module database schemas.
    $this->installSchema('search_api', ['search_api_item']);
    $this->installSchema('user', ['users_data']);

    // Setup: Install required entity schemas.
    $this->installEntitySchema('entity_test_mulrev_changed');
    $this->installEntitySchema('search_api_task');

    // Setup: Install required module configuration.
    $this->installConfig('search_api_test_example_content');
    $this->installConfig('search_api');
    $this->installConfig([
      'elasticsearch_connector',
      'elasticsearch_connector_test',
    ]);

    // Setup: Do not use a batch for tracking the initial items after creating
    // an index when running the tests via the GUI. Otherwise, it seems Drupal's
    // Batch API gets confused and the test fails.
    if (!Utility::isRunningInCli()) {
      \Drupal::state()->set('search_api_use_tracking_batch', FALSE);
    }

    // Setup: Set up the example content entity bundles.
    $this->setUpExampleStructure();

    // Setup: Skip the test if the server is unavailable.
    if (!$this->serverAvailable()) {
      $this->markTestSkipped('ElasticSearch server not available');
    }
  }

  /**
   * Test that we can successfully change index settings on a real index.
   *
   * @see \Drupal\elasticsearch_connector\SearchAPI\BackendClient::updateSettings()
   */
  public function testUpdateSettings(): void {
    // Setup: Get the index we will test with.
    $index = Index::load($this->indexId);

    // Setup: Get Elasticsearch Connector's backend client.
    $serverBackend = Server::load($this->serverId)->getBackend();
    $this->assertInstanceOf(ElasticSearchBackend::class, $serverBackend);

    // Setup: Get a raw Elasticsearch client.
    $rawElasticsearchClient = $serverBackend->getClient();

    // Setup: Register that we want to rename a field.
    $index->renameField('width', 'htdiw');

    // SUT: Save the index: this should run BackendClient::updateSettings().
    // Note that we don't currently have a way to verify it calls that function,
    // since we cannot easily substitute a mock BackendClient.
    $index->save();

    // Assert: The raw Elasticsearch client should report that there is now a
    // field named 'htdiw' and the old 'width' field no longer exists.
    $indexMappings = $rawElasticsearchClient->indices()->getMapping([
      'index' => $this->indexId,
    ])->asArray();
    $this->assertArrayHasKey('htdiw', $indexMappings['test_elasticsearch_index']['mappings']['properties']);
    $this->assertArrayNotHasKey('width', $indexMappings['test_elasticsearch_index']['mappings']['properties']);
  }

}
