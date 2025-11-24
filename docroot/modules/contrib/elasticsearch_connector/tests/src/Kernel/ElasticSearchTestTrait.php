<?php

declare(strict_types=1);

namespace Drupal\Tests\elasticsearch_connector\Kernel;

use Drupal\elasticsearch_connector\Plugin\search_api\backend\ElasticSearchBackend;
use Drupal\search_api\Entity\Index;
use Drupal\search_api\Entity\Server;
use Elastic\Elasticsearch\Exception\ServerResponseException;

/**
 * Provides trait methods for ElasticSearch Kernel tests.
 */
trait ElasticSearchTestTrait {

  /**
   * Check if the server is available.
   */
  protected function serverAvailable(): bool {
    try {
      /** @var \Drupal\search_api\Entity\Server $server */
      $server = Server::load($this->serverId);
      if ($server->getBackend()->isAvailable()) {
        return TRUE;
      }
    }
    catch (ServerResponseException $e) {
      // Ignore.
    }
    return FALSE;
  }

  /**
   * Retrieves the search index used by this test.
   *
   * @return \Drupal\search_api\IndexInterface|null
   *   The search index.
   */
  protected function getIndex() {
    return Index::load($this->indexId);
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
    $index = $this->getIndex();
    if (!isset($index)) {
      $this->fail("Failed to load index");
    }
    $client = $backend->getBackendClient();
    if ($client->indexExists($index)) {
      $client->removeIndex($index);
    }
    $client->addIndex($index);
  }

  /**
   * Refreshes the indices on the server.
   *
   * This ensures all indexed data is available to searches.
   */
  protected function refreshIndices(): void {
    $backend = $this->getIndex()
      ->getServerInstance()
      ->getBackend();
    if ($backend instanceof ElasticSearchBackend) {
      $backend->getConnector()
        ->getClient()
        ->indices()
        ->refresh();
    }
  }

}
