<?php

declare(strict_types=1);

namespace Drupal\Tests\elasticsearch_connector\Kernel;

use Drupal\elasticsearch_connector_test\EventSubscriber\ElasticSearchEventSubscribers;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\Tests\search_api\Kernel\BackendTestBase;

/**
 * Tests the end-to-end functionality of the backend.
 *
 * @group elasticsearch_connector
 */
class ElasticSearchBackendTest extends BackendTestBase {

  use ElasticSearchTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'elasticsearch_connector',
    'elasticsearch_connector_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $serverId = 'elasticsearch_server';

  /**
   * {@inheritdoc}
   */
  protected $indexId = 'test_elasticsearch_index';

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    parent::setUp();
    $this->installConfig([
      'elasticsearch_connector',
      'elasticsearch_connector_test',
    ]);
    if (!$this->serverAvailable()) {
      $this->markTestSkipped("ElasticSearch server not available");
    }
  }

  /**
   * Tests various indexing scenarios for the search backend.
   *
   * Uses a single method to save time.
   */
  public function testBackend() {
    $this->recreateIndex();
    parent::testBackend();
  }

  /**
   * Re-creates the index.
   *
   * @throws \Drupal\search_api\SearchApiException
   */
  protected function recreateIndex() {
    $server = Server::load($this->serverId);
    /** @var \Drupal\elasticsearch_connector\Plugin\search_api\backend\ElasticSearchBackend $backend */
    $backend = $server->getBackend();
    $index = Index::load($this->indexId);
    $client = $backend->getBackendClient();
    if ($client->indexExists($index)) {
      $client->removeIndex($index);
    }
    $client->addIndex($index);
    $this->assertEquals($this->indexId, ElasticSearchEventSubscribers::getIndex()->id());
    // Check that the IndexPreCreate event was triggered.
    $this->assertEquals(['index' => $this->indexId, 'settings' => ['max_ngram_diff' => 25]], ElasticSearchEventSubscribers::getParams());
  }

  /**
   * {@inheritdoc}
   */
  protected function regressionTest2469547() {
    // This test is not allowed in elasticsearch.
    // It will result in : search_phase_execution_exception.
    // Aggregations are not allowed on text.
  }

  /**
   * {@inheritdoc}
   */
  protected function addTestEntity($id, array $values) {
    // Note these values are re-used in
    // \Drupal\elasticsearch_connector_test\Drush\Commands\ElasticsearchConnectorTestCommands::addSearchApiTestContent().
    $created = match ($id) {
      1 => strtotime("2003-07-10T00:00:00Z"),
      2 => strtotime("2008-06-28T00:00:00Z"),
      3 => strtotime("2012-06-11T00:00:00Z"),
      4 => strtotime("2016-09-16T00:00:00Z"),
      5 => strtotime("2018-04-02T00:00:00Z"),
      default => NULL,
    };

    if ($created) {
      $values["created"] = $created;
    }

    return parent::addTestEntity($id, $values);
  }

  /**
   * {@inheritdoc}
   */
  protected function searchSuccess() {
    // Run the upstream tests.
    parent::searchSuccess();

    // In addition to the upstream tests, we also need to test our custom code
    // to handle epoch_second format in
    // \Drupal\elasticsearch_connector\SearchAPI\Query\FilterBuilder::getRangeFilter().
    //
    // We need to test with both ISO-8601 strings (e.g.: '2008-06-27'), and UNIX
    // integer timestamps (i.e.: output from \strtotime()); using the 'created'-
    // dates-to-entity-id mappings from addTestEntity().
    //
    // Test BETWEEN operator. Note that 2008-06-27 is one day before ID=2; and
    // 2012-06-12 is one day after ID=3.
    $results = $this->buildSearch()
      ->addCondition('created', [
        '2008-06-27T00:00:00Z',
        '2012-06-12T00:00:00Z',
      ], 'BETWEEN')
      ->execute();
    $this->assertEquals($this->getItemIds([
      2,
      3,
    ]), array_keys($results->getResultItems()), 'Search for created range returned correct results.');
    $results = $this->buildSearch()
      ->addCondition('created', [
        strtotime('2008-06-27T00:00:00Z'),
        strtotime('2012-06-12T00:00:00Z'),
      ], 'BETWEEN')
      ->execute();
    $this->assertEquals($this->getItemIds([
      2,
      3,
    ]), array_keys($results->getResultItems()), 'Search for created range returned correct results.');

    // Test NOT BETWEEN operator. Note that 2008-06-26 is two days before ID=2;
    // and 2012-06-12 is one day after ID=3.
    $results = $this->buildSearch()
      ->addCondition('created', [
        '2008-06-26T00:00:00Z',
        '2012-06-12T00:00:00Z',
      ], 'NOT BETWEEN')
      ->execute();
    $this->assertEquals($this->getItemIds([
      1,
      4,
      5,
    ]), array_keys($results->getResultItems()), 'Search for created range returned correct results.');
    $results = $this->buildSearch()
      ->addCondition('created', [
        strtotime('2008-06-26T00:00:00Z'),
        strtotime('2012-06-12T00:00:00Z'),
      ], 'NOT BETWEEN')
      ->execute();
    $this->assertEquals($this->getItemIds([
      1,
      4,
      5,
    ]), array_keys($results->getResultItems()), 'Search for created range returned correct results.');

    // Test > (Is greater than) operator. Note that 2008-06-28 the date of ID=2,
    // so ID=2 should not appear in the search results.
    $results = $this->buildSearch()
      ->addCondition('created',
        '2008-06-28T00:00:00Z',
        '>')
      ->execute();
    $this->assertEquals($this->getItemIds([
      3,
      4,
      5,
    ]), array_keys($results->getResultItems()), 'Search for created range returned correct results.');
    $results = $this->buildSearch()
      ->addCondition('created',
        strtotime('2008-06-28T00:00:00Z'),
        '>')
      ->execute();
    $this->assertEquals($this->getItemIds([
      3,
      4,
      5,
    ]), array_keys($results->getResultItems()), 'Search for created range returned correct results.');

    // Test >= (Is greater than or equal to) operator. Note that 2008-06-28 is
    // the date of ID=2, so ID=2 should appear in the search results.
    $results = $this->buildSearch()
      ->addCondition('created',
        '2008-06-28T00:00:00Z',
        '>=')
      ->execute();
    $this->assertEquals($this->getItemIds([
      2,
      3,
      4,
      5,
    ]), array_keys($results->getResultItems()), 'Search for created range returned correct results.');
    $results = $this->buildSearch()
      ->addCondition('created',
        strtotime('2008-06-28T00:00:00Z'),
        '>=')
      ->execute();
    $this->assertEquals($this->getItemIds([
      2,
      3,
      4,
      5,
    ]), array_keys($results->getResultItems()), 'Search for created range returned correct results.');

    // Test < (Is less than) operator. Note 2008-06-28 is the date of ID=2, so
    // ID=2 should not appear in the search results.
    $results = $this->buildSearch()
      ->addCondition('created',
        '2008-06-28T00:00:00Z',
        '<')
      ->execute();
    $this->assertEquals($this->getItemIds([
      1,
    ]), array_keys($results->getResultItems()), 'Search for created range returned correct results.');
    $results = $this->buildSearch()
      ->addCondition('created',
        strtotime('2008-06-28T00:00:00Z'),
        '<')
      ->execute();
    $this->assertEquals($this->getItemIds([
      1,
    ]), array_keys($results->getResultItems()), 'Search for created range returned correct results.');

    // Test <= (Is less than or equal to) operator. Note 2008-06-28 is the date
    // of ID=2, so ID=2 should appear in the search results.
    $results = $this->buildSearch()
      ->addCondition('created',
        '2008-06-28T00:00:00Z',
        '<=')
      ->execute();
    $this->assertEquals($this->getItemIds([
      1,
      2,
    ]), array_keys($results->getResultItems()), 'Search for created range returned correct results.');
    $results = $this->buildSearch()
      ->addCondition('created',
        strtotime('2008-06-28T00:00:00Z'),
        '<=')
      ->execute();
    $this->assertEquals($this->getItemIds([
      1,
      2,
    ]), array_keys($results->getResultItems()), 'Search for created range returned correct results.');
  }

  /**
   * {@inheritdoc}
   */
  protected function checkServerBackend() {
    // No-op.
  }

  /**
   * {@inheritdoc}
   */
  protected function updateIndex() {
    $server = Server::load($this->serverId);
    /** @var \Drupal\elasticsearch_connector\Plugin\search_api\backend\ElasticSearchBackend $backend */
    $backend = $server->getBackend();
    $index = Index::load($this->indexId);
    $client = $backend->getBackendClient();
    if ($client->indexExists($index)) {
      $client->removeIndex($index);
    }
    $client->addIndex($index);
    $this->assertEquals($this->indexId, ElasticSearchEventSubscribers::getIndex()->id());

  }

  /**
   * {@inheritdoc}
   */
  protected function checkSecondServer() {
    // No-op.
  }

  /**
   * {@inheritdoc}
   */
  protected function checkModuleUninstall() {
    // See whether clearing the server works.
    // Regression test for #2156151.
    $server = Server::load($this->serverId);
    $index = Index::load($this->indexId);
    $server->getBackend()->removeIndex($index);

    $query = $this->buildSearch();
    $results = $query->execute();

    $this->assertEquals(0, $results->getResultCount());
  }

}
