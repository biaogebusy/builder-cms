<?php

declare(strict_types=1);

namespace Drupal\Tests\elasticsearch_connector\Functional;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;

/**
 * Test deleting ElasticSearch Connector 8.x-7.x cluster configuration.
 *
 * @see \elasticsearch_connector_update_9802()
 *
 * @group elasticsearch_connector
 */
class ElasticsearchConnectorUpdate9802Test extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'update_test_schema',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles() {
    $core_dump_file = glob(DRUPAL_ROOT . '/core/modules/system/tests/fixtures/update/drupal-*.bare.standard.php.gz')[0];
    $this->databaseDumpFiles = [
      $core_dump_file,
      __DIR__ . '/../../../tests/fixtures/update/drupal-9_4_0--search_api-1.31.0--elasticsearch_connector-8.x-7.0-alpha4.php',
    ];
  }

  /**
   * Tests whether elasticsearch_connector_update_9802() works correctly.
   */
  public function testUpdate9802(): void {
    $configFactory = $this->container->get('config.factory');

    $clusterConfigNamesBefore = $configFactory->listAll('elasticsearch_connector.cluster.');
    $this->assertCount(5, $clusterConfigNamesBefore);

    $this->runUpdates();

    $clusterConfigNamesAfter = $configFactory->listAll('elasticsearch_connector.cluster.');
    $this->assertCount(0, $clusterConfigNamesAfter);
  }

}
