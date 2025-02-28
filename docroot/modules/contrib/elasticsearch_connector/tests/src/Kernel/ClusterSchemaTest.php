<?php

namespace Drupal\Tests\elasticsearch_connector\Kernel;

use Drupal\Core\Config\Schema\SchemaCheckTrait;
use Drupal\elasticsearch_connector\Entity\Cluster;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the cluster schema definition.
 *
 * @group elasticsearch_connector
 */
class ClusterSchemaTest extends KernelTestBase {

  use SchemaCheckTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = [
    'elasticsearch_connector',
  ];

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    $this->cluster = Cluster::create([
      'cluster_id' => 'test_cluster',
      'name' => 'Test cluster',
      'status' => TRUE,
      'url' => 'http://elasticsearch:9200',
      'proxy' => '',
      'options' => [
        'multiple_nodes_connection' => FALSE,
        'use_authentication' => TRUE,
        'authentication_type' => 'Basic',
        'username' => 'some_username',
        'password' => 'test1234',
        'timeout' => 5,
        'rewrite' => [
          'rewrite_index' => TRUE,
          'index' => [
            'prefix' => '',
            'suffix' => '',
          ],
        ],
      ],
      'locked' => FALSE,
    ]);
    $this->cluster->save();
  }

  /**
   * Tests that the cluster schema definition is valid.
   */
  public function testClusterSchema() {
    $config_name = 'elasticsearch_connector.cluster.test_cluster';
    $config_data = $this->config($config_name)->get();
    $config_typed = \Drupal::service('config.typed');
    $this->assertTrue($this->checkConfigSchema($config_typed, $config_name, $config_data), 'Cluster schema is valid');
  }
}
