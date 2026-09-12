<?php

declare(strict_types=1);

namespace Drupal\Tests\elasticsearch_connector\Functional;

use Drupal\Core\Url;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\ServerInterface;
use Drupal\Tests\search_api\Functional\SearchApiBrowserTestBase;

/**
 * Test the Elasticsearch Type Boost plugin's configuration form.
 *
 * @coversDefaultClass \Drupal\elasticsearch_connector\Plugin\search_api\processor\ElasticsearchTypeBoost
 *
 * @group elasticsearch_connector
 */
class ElasticsearchTypeBoostConfigFormTest extends SearchApiBrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'search_api',
    'search_api_test',
    'elasticsearch_connector',
  ];

  /**
   * {@inheritdoc}
   */
  public function getTestIndex(): IndexInterface {
    $this->indexId = 'webtest_index';
    $index = Index::load($this->indexId);
    if (!$index) {
      $index = Index::create([
        'id' => $this->indexId,
        'name' => 'WebTest index',
        'description' => 'WebTest index description',
        'server' => 'webtest_server',
        'field_settings' => [
          'body' => [
            'label' => 'Body',
            'datasource_id' => 'entity:node',
            'property_path' => 'body',
            'type' => 'text',
            'dependencies' => [
              'config' => ['field.storage.node.body'],
            ],
          ],
        ],
        'datasource_settings' => [
          'entity:node' => [],
        ],
      ]);
      $index->save();
    }

    return $index;
  }

  /**
   * {@inheritdoc}
   */
  public function getTestServer(): ServerInterface {
    $server = Server::load('webtest_server');
    if (!$server) {
      $server = Server::create([
        'id' => 'webtest_server',
        'name' => 'WebTest server',
        'description' => 'WebTest server description',
        'backend' => 'elasticsearch',
        'backend_config' => [
          'connector' => 'standard',
          'connector_config' => [
            'url' => 'http://elasticsearch:9200',
            'enable_debug_logging' => FALSE,
          ],
          'advanced' => [
            'fuzziness' => 'auto',
            'prefix' => '',
            'suffix' => '',
            'synonyms' => [],
          ],
        ],
      ]);
      $server->save();
    }

    return $server;
  }

  /**
   * Test the Elasticsearch Type Boost plugin's configuration form.
   */
  public function testTypeBoostProcessorForm(): void {
    // Setup: Create an index and server.
    $this->getTestServer();
    $this->getTestIndex();

    // Setup: Log in as a user account that can configure the index processors.
    $this->drupalLogin($this->adminUser);

    // SUT: Visit the processor page.
    $this->drupalGet(Url::fromRoute('entity.search_api_index.processors', [
      'search_api_index' => $this->indexId,
    ]));

    // Assert: Test that the fields exist.
    $this->assertSession()->fieldExists('status[elasticsearch_type_boost]');
    $this->assertSession()->fieldExists('processors[elasticsearch_type_boost][settings][boosts][entity:node][datasource_boost]');
    $this->assertSession()->fieldExists('processors[elasticsearch_type_boost][settings][boosts][entity:node][bundle_boosts][article]');
    $this->assertSession()->fieldExists('processors[elasticsearch_type_boost][settings][boosts][entity:node][bundle_boosts][page]');

    // SUT: Change as many fields from the default as we can.
    $this->submitForm([
      'status[elasticsearch_type_boost]' => TRUE,
      'processors[elasticsearch_type_boost][settings][boosts][entity:node][datasource_boost]' => '1.10',
      'processors[elasticsearch_type_boost][settings][boosts][entity:node][bundle_boosts][article]' => '1.20',
      'processors[elasticsearch_type_boost][settings][boosts][entity:node][bundle_boosts][page]' => '1.30',
    ], 'Save');

    // Assertions: Ensure the page can be saved successfully.
    $this->assertSession()->statusMessageContains('The indexing workflow was successfully edited.');
  }

}
