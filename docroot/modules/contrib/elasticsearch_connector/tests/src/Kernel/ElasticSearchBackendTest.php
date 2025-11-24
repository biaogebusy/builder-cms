<?php

declare(strict_types=1);

namespace Drupal\Tests\elasticsearch_connector\Kernel;

use Drupal\Tests\search_api\Kernel\BackendTestBase;
use Drupal\elasticsearch_connector_test\EventSubscriber\ElasticSearchEventSubscribers;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Drupal\search_api\SearchApiException;

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
  }

  /**
   * {@inheritdoc}
   */
  protected function tearDown(): void {
    parent::tearDown();
    \Drupal::unsetContainer();
    $this->container = NULL;
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

    $created = match ($id) {
      1 => strtotime("2003-07-10 00:00:00"),
      2 => strtotime("2008-06-28 00:00:00"),
      3 => strtotime("2012-06-11 00:00:00"),
      4 => strtotime("2016-09-16 00:00:00"),
      5 => strtotime("2018-04-02 00:00:00"),
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
    $this->expectException(SearchApiException::class);
    $query->execute();
  }

}
