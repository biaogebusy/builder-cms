<?php

declare(strict_types=1);

namespace Drupal\Tests\elasticsearch_connector\Functional;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;

/**
 * Test deleting ElasticSearch Connector 8.x-7.x index configuration.
 *
 * @see \elasticsearch_connector_update_9801()
 *
 * @group elasticsearch_connector
 */
class ElasticsearchConnectorUpdate9801Test extends UpdatePathTestBase {

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
   * Tests whether elasticsearch_connector_update_9801() works correctly.
   */
  public function testUpdate9801(): void {
    $configFactory = $this->container->get('config.factory');

    $indexConfigNamesBefore = $configFactory->listAll('elasticsearch_connector.index.');
    $this->assertCount(6, $indexConfigNamesBefore);

    $this->runUpdates();

    $indexConfigNamesAfter = $configFactory->listAll('elasticsearch_connector.index.');
    $this->assertCount(0, $indexConfigNamesAfter);
  }

}
