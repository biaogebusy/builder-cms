<?php

declare(strict_types=1);

namespace Drupal\Tests\elasticsearch_connector\Functional;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;

/**
 * Test migrating ElasticSearch Connector 8.x-7.x server configuration to 8.0.x.
 *
 * @see \elasticsearch_connector_update_9800()
 *
 * @group elasticsearch_connector
 */
class ElasticsearchConnectorUpdate9800Test extends UpdatePathTestBase {

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
   * Tests whether elasticsearch_connector_update_9800() works correctly.
   */
  public function testUpdate9800(): void {
    // Check the ElasticSearch Cluster configuration to be migrated.
    $elasticSearchClusters = $this->getElasticSearchClusters();
    $this->assertCount(5, $elasticSearchClusters);
    $this->assertEquals([
      'use_authentication' => TRUE,
      'authentication_type' => 'Basic',
      'url' => 'http://elasticsearch:9200/abrupt_strawberry',
      'username' => 'green',
      'password' => 'laugh',
      'options.rewrite.index.prefix' => 'brainy',
      'options.rewrite.index.suffix' => '',
    ], $elasticSearchClusters['abrupt_strawberry']);
    $this->assertEquals([
      'use_authentication' => TRUE,
      'authentication_type' => 'Digest',
      'url' => 'http://elasticsearch:9200/acceptable_blueberry',
      'username' => 'grumpy',
      'password' => 'orange',
      'options.rewrite.index.prefix' => '',
      'options.rewrite.index.suffix' => '',
    ], $elasticSearchClusters['acceptable_blueberry']);
    $this->assertEquals([
      'use_authentication' => TRUE,
      'authentication_type' => 'NTLM',
      'url' => 'http://elasticsearch:9200/bustling_grapefruit',
      'username' => 'gigantic',
      'password' => 'distance',
      'options.rewrite.index.prefix' => '',
      'options.rewrite.index.suffix' => 'parched',
    ], $elasticSearchClusters['bustling_grapefruit']);
    $this->assertEquals([
      'use_authentication' => FALSE,
      'authentication_type' => 'Basic',
      'url' => 'http://elasticsearch:9200/tacky_pear',
      'username' => '',
      'password' => '',
      'options.rewrite.index.prefix' => 'grumpy',
      'options.rewrite.index.suffix' => 'raspy',
    ], $elasticSearchClusters['tacky_pear']);
    $this->assertEquals([
      'use_authentication' => FALSE,
      'authentication_type' => 'Basic',
      'url' => 'http://elasticsearch:9200/best_kiwi',
      'username' => '',
      'password' => '',
      'options.rewrite.index.prefix' => '',
      'options.rewrite.index.suffix' => '',
    ], $elasticSearchClusters['best_kiwi']);

    // Check the Search API Server configuration to be migrated.
    $searchApiServersBefore = $this->getSearchApiServers();
    $this->assertCount(6, $searchApiServersBefore);
    $this->assertEquals([
      'backend' => 'elasticsearch',
      'backend_config.cluster_settings.cluster' => 'tacky_pear',
      'backend_config.fuzziness' => '5',
      'backend_config.advanced.fuzziness' => NULL,
      'backend_config.advanced.prefix' => NULL,
      'backend_config.advanced.suffix' => NULL,
      'backend_config.advanced.synonyms' => NULL,
      'backend_config.connector' => NULL,
      'backend_config.connector_config.password' => NULL,
      'backend_config.connector_config.url' => NULL,
      'backend_config.connector_config.username' => NULL,
    ], $searchApiServersBefore['circular_opinion']);
    $this->assertEquals([
      'backend' => 'elasticsearch',
      'backend_config.cluster_settings.cluster' => 'bustling_grapefruit',
      'backend_config.fuzziness' => '4',
      'backend_config.advanced.fuzziness' => NULL,
      'backend_config.advanced.prefix' => NULL,
      'backend_config.advanced.suffix' => NULL,
      'backend_config.advanced.synonyms' => NULL,
      'backend_config.connector' => NULL,
      'backend_config.connector_config.password' => NULL,
      'backend_config.connector_config.url' => NULL,
      'backend_config.connector_config.username' => NULL,
    ], $searchApiServersBefore['hexagonal_system']);
    $this->assertEquals([
      'backend' => 'elasticsearch',
      'backend_config.cluster_settings.cluster' => 'acceptable_blueberry',
      'backend_config.fuzziness' => 'auto',
      'backend_config.advanced.fuzziness' => NULL,
      'backend_config.advanced.prefix' => NULL,
      'backend_config.advanced.suffix' => NULL,
      'backend_config.advanced.synonyms' => NULL,
      'backend_config.connector' => NULL,
      'backend_config.connector_config.password' => NULL,
      'backend_config.connector_config.url' => NULL,
      'backend_config.connector_config.username' => NULL,
    ], $searchApiServersBefore['octagonal_rhythm']);
    $this->assertEquals([
      'backend' => 'elasticsearch',
      'backend_config.cluster_settings.cluster' => 'abrupt_strawberry',
      'backend_config.fuzziness' => '1',
      'backend_config.advanced.fuzziness' => NULL,
      'backend_config.advanced.prefix' => NULL,
      'backend_config.advanced.suffix' => NULL,
      'backend_config.advanced.synonyms' => NULL,
      'backend_config.connector' => NULL,
      'backend_config.connector_config.password' => NULL,
      'backend_config.connector_config.url' => NULL,
      'backend_config.connector_config.username' => NULL,
    ], $searchApiServersBefore['oval_decision']);
    $this->assertEquals([
      'backend' => 'elasticsearch',
      'backend_config.cluster_settings.cluster' => 'tacky_pear',
      'backend_config.fuzziness' => '2',
      'backend_config.advanced.fuzziness' => NULL,
      'backend_config.advanced.prefix' => NULL,
      'backend_config.advanced.suffix' => NULL,
      'backend_config.advanced.synonyms' => NULL,
      'backend_config.connector' => NULL,
      'backend_config.connector_config.password' => NULL,
      'backend_config.connector_config.url' => NULL,
      'backend_config.connector_config.username' => NULL,
    ], $searchApiServersBefore['square_idea']);
    $this->assertEquals([
      'backend' => 'elasticsearch',
      'backend_config.cluster_settings.cluster' => 'abrupt_strawberry',
      'backend_config.fuzziness' => '3',
      'backend_config.advanced.fuzziness' => NULL,
      'backend_config.advanced.prefix' => NULL,
      'backend_config.advanced.suffix' => NULL,
      'backend_config.advanced.synonyms' => NULL,
      'backend_config.connector' => NULL,
      'backend_config.connector_config.password' => NULL,
      'backend_config.connector_config.url' => NULL,
      'backend_config.connector_config.username' => NULL,
    ], $searchApiServersBefore['triangular_discovery']);

    $this->runUpdates();

    // Test post-conditions.
    $searchApiServersAfter = $this->getSearchApiServers();
    $this->assertCount(6, $searchApiServersAfter);
    $this->assertEquals([
      'backend' => 'elasticsearch',
      'backend_config.cluster_settings.cluster' => NULL,
      'backend_config.fuzziness' => NULL,
      'backend_config.advanced.fuzziness' => '5',
      'backend_config.advanced.prefix' => 'grumpy_',
      'backend_config.advanced.suffix' => '_raspy',
      'backend_config.advanced.synonyms' => NULL,
      'backend_config.connector' => 'standard',
      'backend_config.connector_config.password' => NULL,
      'backend_config.connector_config.url' => 'http://elasticsearch:9200/tacky_pear',
      'backend_config.connector_config.username' => NULL,
    ], $searchApiServersAfter['circular_opinion']);
    // Note the following defaults to the 'standard' connector, because there is
    // no longer an "NTLM" authentication type.
    $this->assertEquals([
      'backend' => 'elasticsearch',
      'backend_config.cluster_settings.cluster' => NULL,
      'backend_config.fuzziness' => NULL,
      'backend_config.advanced.fuzziness' => '4',
      'backend_config.advanced.prefix' => '',
      'backend_config.advanced.suffix' => '_parched',
      'backend_config.advanced.synonyms' => NULL,
      'backend_config.connector' => 'standard',
      'backend_config.connector_config.password' => NULL,
      'backend_config.connector_config.url' => 'http://elasticsearch:9200/bustling_grapefruit',
      'backend_config.connector_config.username' => NULL,
    ], $searchApiServersAfter['hexagonal_system']);
    // Note the following defaults to the 'standard' connector, because there is
    // no longer a "Digest" authentication type.
    $this->assertEquals([
      'backend' => 'elasticsearch',
      'backend_config.cluster_settings.cluster' => NULL,
      'backend_config.fuzziness' => NULL,
      'backend_config.advanced.fuzziness' => 'auto',
      'backend_config.advanced.prefix' => '',
      'backend_config.advanced.suffix' => '',
      'backend_config.advanced.synonyms' => NULL,
      'backend_config.connector' => 'standard',
      'backend_config.connector_config.password' => NULL,
      'backend_config.connector_config.url' => 'http://elasticsearch:9200/acceptable_blueberry',
      'backend_config.connector_config.username' => NULL,
    ], $searchApiServersAfter['octagonal_rhythm']);
    $this->assertEquals([
      'backend' => 'elasticsearch',
      'backend_config.cluster_settings.cluster' => NULL,
      'backend_config.fuzziness' => NULL,
      'backend_config.advanced.fuzziness' => '1',
      'backend_config.advanced.prefix' => 'brainy_',
      'backend_config.advanced.suffix' => '',
      'backend_config.advanced.synonyms' => NULL,
      'backend_config.connector' => 'basicauth',
      'backend_config.connector_config.password' => 'laugh',
      'backend_config.connector_config.url' => 'http://elasticsearch:9200/abrupt_strawberry',
      'backend_config.connector_config.username' => 'green',
    ], $searchApiServersAfter['oval_decision']);
    $this->assertEquals([
      'backend' => 'elasticsearch',
      'backend_config.cluster_settings.cluster' => NULL,
      'backend_config.fuzziness' => NULL,
      'backend_config.advanced.fuzziness' => '2',
      'backend_config.advanced.prefix' => 'grumpy_',
      'backend_config.advanced.suffix' => '_raspy',
      'backend_config.advanced.synonyms' => NULL,
      'backend_config.connector' => 'standard',
      'backend_config.connector_config.password' => NULL,
      'backend_config.connector_config.url' => 'http://elasticsearch:9200/tacky_pear',
      'backend_config.connector_config.username' => NULL,
    ], $searchApiServersAfter['square_idea']);
    $this->assertEquals([
      'backend' => 'elasticsearch',
      'backend_config.cluster_settings.cluster' => NULL,
      'backend_config.fuzziness' => NULL,
      'backend_config.advanced.fuzziness' => '3',
      'backend_config.advanced.prefix' => 'brainy_',
      'backend_config.advanced.suffix' => '',
      'backend_config.advanced.synonyms' => NULL,
      'backend_config.connector' => 'basicauth',
      'backend_config.connector_config.password' => 'laugh',
      'backend_config.connector_config.url' => 'http://elasticsearch:9200/abrupt_strawberry',
      'backend_config.connector_config.username' => 'green',
    ], $searchApiServersAfter['triangular_discovery']);
  }

  /**
   * Get information about the ElasticSearch Clusters in configuration.
   *
   * @return array
   *   An associative array of ElasticSearch Cluster configuration information
   *   relevant to migration, containing:
   *   - use_authentication: (bool) Whether to use authentication for this
   *     cluster.
   *   - authentication_type: (string) Which authentication type to use for this
   *     cluster.
   *   - url: (string) The URL to access this cluster.
   *   - username: (string) The username to use for authentication to this
   *     cluster.
   *   - password: (string) The password to use for authentication to this
   *     cluster.
   *   - options.rewrite.index.prefix: (string) A string to use as a prefix for
   *     indexes on this cluster.
   *   - options.rewrite.index.suffix: (string) A string to use as a suffix for
   *     indexes on this cluster.
   */
  protected function getElasticSearchClusters(): array {
    $clusterInfo = [];

    $configFactory = $this->container->get('config.factory');
    $clusterNames = $configFactory->listAll('elasticsearch_connector.cluster.');
    foreach ($clusterNames as $clusterName) {
      $config = $this->config($clusterName);
      $clusterInfo[$config->get('cluster_id')] = [
        'use_authentication' => (bool) $config->get('options.use_authentication'),
        'authentication_type' => $config->get('options.authentication_type'),
        'url' => $config->get('url'),
        'username' => $config->get('options.username'),
        'password' => $config->get('options.password'),
        'options.rewrite.index.prefix' => $config->get('options.rewrite.index.prefix'),
        'options.rewrite.index.suffix' => $config->get('options.rewrite.index.suffix'),
      ];
    }

    return $clusterInfo;
  }

  /**
   * Get information about the Search API Servers in configuration.
   *
   * @return array
   *   An associative array of Search API Server configuration information
   *   relevant to migration, containing:
   *   - backend: (string)
   *   - backend_config.cluster_settings.cluster: (string) The ElasticSearch
   *     Cluster configuration to use for this Search API server.
   *   - backend_config.fuzziness: (string) How similar search queries must be
   *     to words in order to match.
   *   - backend_config.advanced.prefix: (string) A string to use as a prefix
   *     for indexes on this server.
   *   - backend_config.advanced.suffix: (string) A string to use as a suffix
   *     for indexes on this server.
   *   - backend_config.advanced.synonyms: (string[]) An array of synonyms to
   *     use for queries to this server.
   *   - backend_config.connector: (string) The connector to use to connect to
   *     this server.
   *   - backend_config.connector_config.password: (string) The password to use
   *     for authentication to this server.
   *   - backend_config.connector_config.url: (string) The URL to access this
   *     server.
   *   - backend_config.connector_config.username: (string) The username to use
   *     for authentication to this server.
   */
  protected function getSearchApiServers(): array {
    $serverInfo = [];

    $configFactory = $this->container->get('config.factory');
    $serverNames = $configFactory->listAll('search_api.server.');
    foreach ($serverNames as $serverName) {
      $config = $this->config($serverName);
      $serverInfo[$config->get('id')] = [
        'backend' => $config->get('backend'),
        'backend_config.cluster_settings.cluster' => $config->get('backend_config.cluster_settings.cluster'),
        'backend_config.fuzziness' => $config->get('backend_config.fuzziness'),
        'backend_config.advanced.fuzziness' => $config->get('backend_config.advanced.fuzziness'),
        'backend_config.advanced.prefix' => $config->get('backend_config.advanced.prefix'),
        'backend_config.advanced.suffix' => $config->get('backend_config.advanced.suffix'),
        'backend_config.advanced.synonyms' => $config->get('backend_config.advanced.synonyms'),
        'backend_config.connector' => $config->get('backend_config.connector'),
        'backend_config.connector_config.password' => $config->get('backend_config.connector_config.password'),
        'backend_config.connector_config.url' => $config->get('backend_config.connector_config.url'),
        'backend_config.connector_config.username' => $config->get('backend_config.connector_config.username'),
      ];
    }

    return $serverInfo;
  }

}
