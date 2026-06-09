<?php

namespace Drupal\test_ai_vdb_provider_mysql\Plugin\VdbProvider;

use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\Database\Database;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\AiVdbProvider;
use Drupal\ai\Base\AiVdbProviderClientBase;
use Drupal\ai\Enum\VdbSimilarityMetrics;
use Drupal\search_api\Query\QueryInterface;
use Drupal\test_ai_vdb_provider_mysql\TestVectorTable;
use MHz\MysqlVector\VectorTable;

/**
 * Plugin implementation of the 'Test MySQL AI VDB provider' provider.
 */
#[AiVdbProvider(
  id: 'test_mysql',
  label: new TranslatableMarkup('Test MySQL AI VDB provider'),
)]
class TestAiVdbProviderMySql extends AiVdbProviderClientBase implements ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The initialized MySQL vector table.
   *
   * @var \MHz\MysqlVector\VectorTable|false
   */
  protected $vectorTable;

  /**
   * {@inheritdoc}
   */
  public function getClient(): mixed {
    return [];
  }

  /**
   * Get the MySQL vector table name.
   *
   * @param string $collection_name
   *   The collection name.
   * @param string $database
   *   The database name.
   * @param bool $with_prefix
   *   The vector table class does not know about Drupal prefix. When calling
   *   using Drupal, it automatically adds the prefix.
   *
   * @return string
   *   The vector table name.
   */
  public function getVectorTableName(
    string $collection_name,
    string $database = 'default',
    bool $with_prefix = FALSE,
  ): string {
    $prefix = ($with_prefix ? Database::getConnection()->getPrefix() : '');
    return $prefix . $database . '_' . $collection_name . '_vectors';
  }

  /**
   * Get the MySQL vector table name.
   *
   * @param string $collection_name
   *   The collection name.
   * @param string $database
   *   The database name.
   *
   * @return string
   *   The vector table name.
   */
  public function getMapTableName(
    string $collection_name,
    string $database = 'default',
  ): string {
    return $database . '_' . $collection_name . '_map';
  }

  /**
   * Get the MySQL vector table.
   *
   * @param string $collection_name
   *   The collection name.
   * @param string $database
   *   The database name.
   *
   * @return false|VectorTable
   *   The vector table initialized or false.
   */
  public function getVectorTable(
    string $collection_name,
    string $database = 'default',
  ): false|VectorTable {
    if ($this->vectorTable) {
      return $this->vectorTable;
    }

    // Get the default Drupal database connection.
    $connection = Database::getConnection();
    $options = $connection->getConnectionOptions();

    // Switch to the newly created database.
    $mysqli = new \mysqli(
      $options['host'],
      $options['username'],
      $options['password'],
      $options['database'],
    );

    if ($mysqli->connect_error) {
      $message = $this->t('Failed to connect to MySQL manually: @message', [
        '@message' => $mysqli->connect_error,
      ]);
      $this->messenger->addError($message);
      return FALSE;
    }

    // Create the vector table using the MHz MysqlVector library.
    // The table name here is automatically getting the suffix '_vectors', so we
    // need to strip it from this particular call.
    $name = $this->getVectorTableName($collection_name, $database, TRUE);
    $name = str_replace('_vectors', '', $name);
    $this->vectorTable = new VectorTable($mysqli, $name, 384, 'InnoDB');
    $query = $connection->select('information_schema.tables', 't')
      ->fields('t', ['table_name'])
      ->condition('t.table_schema', $options['database'])
      ->condition('t.table_name', $this->getVectorTableName($collection_name, $database, TRUE))
      ->range(0, 1);
    $result = $query->execute()->fetchField();
    if (!$result) {
      $this->vectorTable->initialize();
    }
    return $this->vectorTable;
  }

  /**
   * {@inheritdoc}
   */
  public function getConfig(): ImmutableConfig {
    $this->configFactory->getEditable('test_ai_vdb_provider_mysql.settings')->setData([
      'test' => 'test',
    ])->save();
    return $this->configFactory->get('test_ai_vdb_provider_mysql.settings');
  }

  /**
   * Set key for authentication of the client.
   *
   * @param mixed $authentication
   *   The authentication.
   */
  public function setAuthentication(mixed $authentication): void {
  }

  /**
   * Get connection data.
   *
   * @return array
   *   The connection data.
   */
  public function getConnectionData() {
    $config = $this->getConfig();
    $output['server'] = $this->configuration['server'] ?? $config->get('server');
    return $output;
  }

  /**
   * {@inheritdoc}
   */
  public function ping(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function isSetup(): bool {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function viewIndexSettings(array $database_settings): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getCollections(string $database = 'default'): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function createCollection(
    string $collection_name,
    int $dimension,
    VdbSimilarityMetrics $metric_type = VdbSimilarityMetrics::CosineSimilarity,
    string $database = 'default',
  ): void {
    $this->dropCollection($collection_name, $database);

    // Get or create the vector table.
    $this->getVectorTable($collection_name, $database);

    // Define the mapping table name.
    $mapping_table_name = $this->getMapTableName($collection_name, $database);

    // Use Drupal schema API to define the table structure.
    $schema = [
      'description' => 'Mapping table for vector table IDs to Drupal entities.',
      'fields' => [
        'vector_table_id' => [
          'type' => 'int',
          'unsigned' => TRUE,
          'not null' => TRUE,
          'description' => 'Primary key mapping to the vector table.',
        ],
        'drupal_long_id' => [
          'type' => 'varchar',
          'length' => 255,
          'not null' => TRUE,
          'description' => 'Long ID for the Drupal entity.',
        ],
        'drupal_entity_id' => [
          'type' => 'varchar',
          'length' => 255,
          'not null' => TRUE,
          'description' => 'ID for the Drupal entity.',
        ],
      ],
      'primary key' => ['vector_table_id'],
      'indexes' => [
        'drupal_long_id' => ['drupal_long_id'],
        'drupal_entity_id' => ['drupal_entity_id'],
      ],
      'engine' => 'InnoDB',
    ];

    // Create the table using the schema API.
    $connection = Database::getConnection($database);
    if (!$connection->schema()->tableExists($mapping_table_name)) {
      $connection->schema()->createTable($mapping_table_name, $schema);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function dropCollection(
    string $collection_name,
    string $database = 'default',
  ): void {
    $connection = Database::getConnection($database);

    // Drop the vector table.
    $vector_table_name = $this->getVectorTableName($collection_name, $database);
    if ($connection->schema()->tableExists($vector_table_name)) {
      $connection->schema()->dropTable($vector_table_name);
    }

    // Drop the mapping table.
    $mapping_table_name = $this->getMapTableName($collection_name, $database);
    if ($connection->schema()->tableExists($mapping_table_name)) {
      $connection->schema()->dropTable($mapping_table_name);
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Exception
   */
  public function insertIntoCollection(
    string $collection_name,
    array $data,
    string $database = 'default',
  ): void {
    $vector_table = $this->getVectorTable($collection_name, $database);
    foreach ($data['vector'] as $vector) {
      $inserted_id = $vector_table->upsert($vector);
      $query = Database::getConnection()->insert($this->getMapTableName($collection_name, $database));
      $query->fields([
        'vector_table_id' => $inserted_id,
        'drupal_long_id' => $data['drupal_long_id'],
        'drupal_entity_id' => $data['drupal_entity_id'],
      ]);
      $query->execute();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function deleteFromCollection(
    string $collection_name,
    array $ids,
    string $database = 'default',
  ): void {
    $this->deleteItems([
      'database_settings' => [
        'database_name' => $database,
        'collection' => $collection_name,
      ],
    ], $ids);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteItems(array $configuration, array $item_ids): void {
    $vdbIds = $this->getVdbIds(
      collection_name: $configuration['database_settings']['collection'],
      drupalIds: $item_ids,
      database: $configuration['database_settings']['database_name'],
    );
    if ($vdbIds) {
      $connection = Database::getConnection();
      $database = $configuration['database_settings']['database_name'];
      $collection_name = $configuration['database_settings']['collection'];
      $vector_table = $this->getVectorTableName($collection_name, $database);
      $map_table = $this->getMapTableName($collection_name, $database);
      if ($connection->schema()->tableExists($map_table)) {
        $query = $connection->delete($map_table);
        $query->condition('vector_table_id', $vdbIds, 'IN');
        $query->execute();
      }
      if ($connection->schema()->tableExists($vector_table)) {
        $query = $connection->delete($vector_table);
        $query->condition('id', $vdbIds, 'IN');
        $query->execute();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function prepareFilters(QueryInterface $query): string {
    return '';
  }

  /**
   * {@inheritdoc}
   */
  public function querySearch(
    string $collection_name,
    array $output_fields,
    mixed $filters = 'id not in [0]',
    int $limit = 10,
    int $offset = 0,
    string $database = 'default',
  ): array {
    // No query search, vector is required.
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function vectorSearch(
    string $collection_name,
    array $vector_input,
    array $output_fields,
    QueryInterface $query,
    mixed $filters = '',
    int $limit = 10,
    int $offset = 0,
    string $database = 'default',
  ): array {
    $vectorTable = $this->getVectorTable($collection_name, $database);
    $testVectorTable = new TestVectorTable($vectorTable);
    $vector_input = reset($vector_input);
    $results = $testVectorTable->search($vector_input, $limit);
    if (!$results) {
      return [];
    }
    return $results;
  }

  /**
   * {@inheritdoc}
   */
  public function getVdbIds(
    string $collection_name,
    array $drupalIds,
    string $database = 'default',
  ): array {
    $map_table = $this->getMapTableName($collection_name, $database);
    $query = Database::getConnection()->select($map_table, 'map');
    $query->addField('map', 'vector_table_id');
    return $query->execute()->fetchCol();
  }

}
