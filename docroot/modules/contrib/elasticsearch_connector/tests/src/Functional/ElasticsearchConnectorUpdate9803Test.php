<?php

namespace Drupal\Tests\elasticsearch_connector\Functional;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;

/**
 * Test deleting old elasticsearch_connector permissions.
 *
 * @see \elasticsearch_connector_update_9803()
 *
 * @group elasticsearch_connector
 */
class ElasticsearchConnectorUpdate9803Test extends UpdatePathTestBase {

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
   * Test whether elasticsearch_connector_update_9803() works correctly.
   */
  public function testUpdate9803(): void {
    $configBefore = $this->container->get('config.factory')->get('user.role.elastic_admin');
    $moduleDependencies = $configBefore->get('dependencies.module');
    $this->assertContains('elasticsearch_connector', $moduleDependencies);
    $permissions = $configBefore->get('permissions');
    $this->assertContains('administer elasticsearch connector', $permissions);
    $this->assertContains('administer elasticsearch cluster', $permissions);
    $this->assertContains('administer elasticsearch index', $permissions);

    $this->runUpdates();

    $configAfter = $this->container->get('config.factory')->get('user.role.elastic_admin');
    $moduleDependencies = $configAfter->get('dependencies.module');
    $this->assertNull($moduleDependencies);
    $permissions = $configAfter->get('permissions');
    $this->assertNotContains('administer elasticsearch connector', $permissions);
    $this->assertNotContains('administer elasticsearch cluster', $permissions);
    $this->assertNotContains('administer elasticsearch index', $permissions);
  }

}
