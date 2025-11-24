<?php

declare(strict_types=1);

namespace Drupal\Tests\elasticsearch_connector\Functional;

use Drupal\Core\Url;
use Drupal\Tests\search_api\Functional\SearchApiBrowserTestBase;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\ServerInterface;

/**
 * Test the ElasticSearch highlighter processor plugin's configuration form.
 *
 * @coversDefaultClass \Drupal\elasticsearch_connector\Plugin\search_api\processor\ElasticsearchHighlighter
 *
 * @group elasticsearch_connector
 */
class ElasticsearchHighlighterConfigFormTest extends SearchApiBrowserTestBase {

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
   * Test that we can use the highlighter processor configuration form.
   *
   * @covers ::buildConfigurationForm
   */
  public function testHighlighterProcessorForm(): void {
    // Setup: Create an index and server.
    $this->getTestServer();
    $this->getTestIndex();

    // Setup: Log in as a user account that can configure the index processors.
    $this->drupalLogin($this->adminUser);

    // Run system under test: Visit the processor page.
    $this->drupalGet(Url::fromRoute('entity.search_api_index.processors', [
      'search_api_index' => $this->indexId,
    ]));

    // Assertions: Test that the fields exist.
    $this->assertSession()->fieldExists('status[elasticsearch_highlight]');
    $this->assertSession()->fieldExists('processors[elasticsearch_highlight][settings][fields][]');
    $this->assertSession()->fieldExists('processors[elasticsearch_highlight][settings][type]');
    $this->assertSession()->fieldExists('processors[elasticsearch_highlight][settings][fragmenter]');
    $this->assertSession()->fieldExists('processors[elasticsearch_highlight][settings][boundary_scanner]');
    $this->assertSession()->fieldExists('processors[elasticsearch_highlight][settings][boundary_scanner_locale]');
    $this->assertSession()->fieldExists('processors[elasticsearch_highlight][settings][pre_tag]');
    $this->assertSession()->fieldExists('processors[elasticsearch_highlight][settings][encoder]');
    $this->assertSession()->fieldExists('processors[elasticsearch_highlight][settings][number_of_fragments]');
    $this->assertSession()->fieldExists('processors[elasticsearch_highlight][settings][fragment_size]');
    $this->assertSession()->fieldExists('processors[elasticsearch_highlight][settings][no_match_size]');
    $this->assertSession()->fieldExists('processors[elasticsearch_highlight][settings][order]');
    $this->assertSession()->fieldExists('processors[elasticsearch_highlight][settings][require_field_match]');
    $this->assertSession()->fieldExists('processors[elasticsearch_highlight][settings][snippet_joiner]');

    // Run system under test: Change as many fields from the default as we can.
    $this->submitForm([
      'status[elasticsearch_highlight]' => TRUE,
      'processors[elasticsearch_highlight][settings][fields][]' => ['body'],
      'processors[elasticsearch_highlight][settings][type]' => 'plain',
      'processors[elasticsearch_highlight][settings][fragmenter]' => 'span',
      'processors[elasticsearch_highlight][settings][boundary_scanner]' => 'word',
      'processors[elasticsearch_highlight][settings][boundary_scanner_locale]' => 'en',
      'processors[elasticsearch_highlight][settings][pre_tag]' => '<mark>',
      'processors[elasticsearch_highlight][settings][encoder]' => 'html',
      'processors[elasticsearch_highlight][settings][number_of_fragments]' => '1',
      'processors[elasticsearch_highlight][settings][fragment_size]' => '1',
      'processors[elasticsearch_highlight][settings][no_match_size]' => '1',
      'processors[elasticsearch_highlight][settings][order]' => 'score',
      'processors[elasticsearch_highlight][settings][require_field_match]' => 0,
      'processors[elasticsearch_highlight][settings][snippet_joiner]' => 'also',
    ], 'Save');

    // Assertions: Ensure the page can be saved successfully.
    $this->assertSession()->statusMessageContains('The indexing workflow was successfully edited.');
  }

}
